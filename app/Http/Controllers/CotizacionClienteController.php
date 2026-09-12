<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularDocumentoComercialRequest;
use App\Http\Requests\GuardarCotizacionClienteRequest;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\CotizacionCliente;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\TipoOrden;
use App\Services\Ventas\CalcularProformaService;
use App\Services\Ventas\ConvertirCotizacionEnOrdenVentaService;
use App\Services\Ventas\SincronizarHojaCostosCotizacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CotizacionClienteController extends Controller
{
    public function __construct(
        private CalcularProformaService $calculador
    ) {}

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(CotizacionCliente::ESTADOS)],
        ]);

        $consulta = CotizacionCliente::query()
            ->with([
                'cliente.tipoCliente',
                'ordenOperacion.tipoOrden',
                'cotizador',
            ])
            ->whereNull('proforma_id')
            ->withCount('detalles');

        if (! empty($filtros['q'])) {
            $termino = trim($filtros['q']);

            $consulta->where(function ($query) use ($termino): void {
                $query->where('codigo', 'like', "%{$termino}%")
                    ->orWhere('codigo_base', 'like', "%{$termino}%")
                    ->orWhere('cliente_documento', 'like', "%{$termino}%")
                    ->orWhere('cliente_nombre', 'like', "%{$termino}%")
                    ->orWhereHas(
                        'ordenOperacion',
                        fn($orden) => $orden->where('codigo_orden', 'like', "%{$termino}%")
                    );
            });
        }

        if (! empty($filtros['estado'])) {
            $consulta->where('estado', $filtros['estado']);
        }

        $cotizaciones = $consulta
            ->latest('fecha_emision')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $resumenBase = CotizacionCliente::query()->whereNull('proforma_id');
        $resumen = [
            'abiertas' => (clone $resumenBase)->where('estado', 'ABIERTA')->count(),
            'cerradas' => (clone $resumenBase)->where('estado', 'CERRADA')->count(),
            'ordenes' => (clone $resumenBase)
                ->where('estado', 'CONVERTIDA_EN_ORDEN')
                ->count(),
            'anuladas' => (clone $resumenBase)->where('estado', 'ANULADA')->count(),
        ];

        return view(
            'cotizaciones_cliente.index',
            compact('cotizaciones', 'resumen')
        );
    }

    public function create(Request $request): View
    {
        $tipoVenta = TipoOrden::query()
            ->where('codigo', 'OV')
            ->where('estado', true)
            ->firstOrFail();
        $cotizacion = new CotizacionCliente([
            'fecha_emision' => today(),
            'moneda' => 'PEN',
            'estado' => 'ABIERTA',
            'origen' => 'DIRECTA_LOGISTICA',
            'tipo_orden_id' => $tipoVenta->id,
        ]);
        $cotizacion->setRelation('detalles', $cotizacion->newCollection());

        return view('cotizaciones_cliente.create', [
            'cotizacion' => $cotizacion,
            'creandoDirecta' => true,
            ...$this->catalogos($request),
        ]);
    }

    public function storeDirecta(
        GuardarCotizacionClienteRequest $request
    ): RedirectResponse {
        $datos = $request->validated();
        $detalles = $datos['detalles'];
        unset($datos['detalles'], $datos['tipo_cambio_comparacion']);

        $cotizacion = DB::transaction(function () use (
            $datos,
            $detalles,
            $request
        ): CotizacionCliente {
            $cliente = Cliente::query()
                ->with('tipoCliente')
                ->findOrFail($datos['cliente_id']);
            $margen = (float) ($cliente->tipoCliente?->porcentaje_ganancia ?? 0);
            $resultado = $this->prepararCotizacion($datos, $detalles)[1];
            $codigoBase = $this->siguienteCodigoBase();

            $cotizacion = CotizacionCliente::query()->create([
                ...$datos,
                ...$resultado['totales'],
                'proforma_id' => null,
                'origen' => 'DIRECTA_LOGISTICA',
                'codigo_base' => $codigoBase,
                'version' => 1,
                'codigo' => $codigoBase . '-VRS1',
                'cliente_documento' => $cliente->documentoVisible(),
                'cliente_nombre' => $cliente->nombreVisible(),
                'margen_cliente_porcentaje' => $margen,
                'estado' => 'ABIERTA',
                'cotizado_por' => $request->user()->id,
            ]);
            $cotizacion->detalles()->createMany(
                collect($resultado['detalles'])
                    ->map(fn(array $detalle): array => [
                        ...$detalle,
                        'componente_id' => null,
                    ])->all()
            );

            return $cotizacion;
        });

        return redirect()
            ->route('cotizaciones-cliente.show', $cotizacion)
            ->with('success', 'Cotización de venta creada como VRS1 abierta.');
    }

    public function store(Request $request, Proforma $proforma): RedirectResponse
    {
        $datos = $request->validate([
            'cliente_id' => [
                'nullable',
                'integer',
                Rule::exists('clientes', 'id')->where('estado', true),
            ],
        ]);

        $clienteId = $proforma->cliente_id ?: ($datos['cliente_id'] ?? null);

        if (! $clienteId) {
            return back()
                ->withErrors(['cliente_id' => 'Selecciona un cliente antes de cotizar.'])
                ->withInput();
        }

        if ($proforma->estado !== 'ENVIADA_A_LOGISTICA') {
            return back()->with('error', 'Esta proforma no está pendiente de cotización.');
        }

        $cotizacion = DB::transaction(function () use (
            $proforma,
            $clienteId,
            $request
        ): CotizacionCliente {
            $proformaBloqueada = Proforma::query()
                ->lockForUpdate()
                ->with('detalles')
                ->findOrFail($proforma->id);

            $ultima = $proformaBloqueada->cotizacionesCliente()
                ->latest('version')
                ->first();

            if ($ultima && $ultima->estado !== 'ANULADA') {
                abort(422, 'La proforma ya tiene una cotización vigente.');
            }

            $cliente = Cliente::query()->with('tipoCliente')->findOrFail($clienteId);
            $margen = (float) ($cliente->tipoCliente?->porcentaje_ganancia ?? 0);
            $detallesVenta = $proformaBloqueada->detalles
                ->where('tratamiento', 'VENTA')
                ->values();

            abort_if(
                $detallesVenta->isEmpty(),
                422,
                'Esta proforma solo contiene préstamos. Confírmala como operación sin cobro.'
            );

            $entradas = $detallesVenta->map(
                function ($detalle) use ($margen): array {
                    $costo = $detalle->costo_referencia === null
                        ? null
                        : (float) $detalle->costo_referencia;
                    $precioSugerido = $costo === null
                        ? null
                        : $this->calculador->precioSugerido($costo, $margen);

                    return [
                        'proforma_detalle_id' => $detalle->id,
                        'producto_id' => $detalle->producto_id,
                        'codigo_producto' => $detalle->codigo_producto,
                        'descripcion' => $detalle->descripcion,
                        'unidad_medida' => $detalle->unidad_medida,
                        'cantidad' => $detalle->cantidad,
                        'costo_referencia' => $costo,
                        'margen_sugerido' => $margen,
                        'precio_unitario' => $precioSugerido > 0
                            ? $precioSugerido
                            : null,
                        'igv_modo' => $detalle->igv_modo,
                        'observacion' => $detalle->observacion,
                    ];
                }
            )->all();
            $resultado = $this->calculador->calcular($entradas, true);
            $codigoBase = $ultima?->codigo_base ?: $this->siguienteCodigoBase();
            $version = $ultima ? $ultima->version + 1 : 1;

            $cotizacion = CotizacionCliente::query()->create([
                'proforma_id' => $proformaBloqueada->id,
                'origen' => 'PROFORMA_ALMACEN',
                'cliente_id' => $cliente->id,
                'tipo_orden_id' => null,
                'cliente_direccion_id' => null,
                'vehiculo_id' => null,
                'descripcion_trabajo' => 'Productos retirados directamente de Almacén según '
                    . $proformaBloqueada->codigo,
                'codigo_base' => $codigoBase,
                'version' => $version,
                'codigo' => $codigoBase . '-VRS' . $version,
                'cliente_documento' => $cliente->documentoVisible(),
                'cliente_nombre' => $cliente->nombreVisible(),
                'fecha_emision' => now()->toDateString(),
                'fecha_validez' => $proformaBloqueada->fecha_validez,
                'moneda' => $proformaBloqueada->moneda,
                'tipo_cambio' => $proformaBloqueada->tipo_cambio,
                'margen_cliente_porcentaje' => $margen,
                ...$resultado['totales'],
                'condiciones_pago' => $proformaBloqueada->condiciones_pago,
                'condiciones_entrega' => $proformaBloqueada->condiciones_entrega,
                'observacion' => $proformaBloqueada->observacion,
                'estado' => 'ABIERTA',
                'cotizado_por' => $request->user()->id,
            ]);
            $cotizacion->detalles()->createMany($resultado['detalles']);
            $proformaBloqueada->update(['estado' => 'COTIZADA']);

            return $cotizacion;
        });

        return redirect()
            ->route('cotizaciones-cliente.edit', $cotizacion)
            ->with('success', 'Cotización creada como versión abierta. Puedes ajustar precios y condiciones.');
    }

    public function show(CotizacionCliente $cotizacionCliente): View
    {
        $this->asegurarCotizacionSimple($cotizacionCliente);
        $cotizacionCliente->load([
            'cliente.tipoCliente',
            'tipoOrden',
            'clienteDireccion',
            'cotizador',
            'cerrador',
            'anulador',
            'ordenOperacion.tipoOrden',
            'detalles.producto',
        ]);
        $versiones = CotizacionCliente::query()
            ->where('codigo_base', $cotizacionCliente->codigo_base)
            ->orderBy('version')
            ->get();
        return view('cotizaciones_cliente.show_simple', [
            'cotizacion' => $cotizacionCliente,
            'versiones' => $versiones,
        ]);
    }

    public function edit(
        Request $request,
        CotizacionCliente $cotizacionCliente
    ): View|RedirectResponse {
        $this->asegurarCotizacionSimple($cotizacionCliente);
        $cotizacionCliente->load('detalles');

        if (! $cotizacionCliente->esEditable()) {
            return redirect()
                ->route('cotizaciones-cliente.show', $cotizacionCliente)
                ->with('error', 'La versión cerrada o anulada ya no puede editarse.');
        }

        if ($cotizacionCliente->detalles->contains('origen_costeo', true)) {
            return redirect()
                ->route('cotizaciones-cliente.show', $cotizacionCliente)
                ->with('error', 'Esta cotización histórica utiliza el costeo avanzado y es de solo lectura en la versión reducida.');
        }

        return view('cotizaciones_cliente.edit', [
            'cotizacion' => $cotizacionCliente,
            'creandoDirecta' => false,
            ...$this->catalogos($request, $cotizacionCliente),
        ]);
    }

    public function update(
        GuardarCotizacionClienteRequest $request,
        CotizacionCliente $cotizacionCliente
    ): RedirectResponse {
        $this->asegurarCotizacionSimple($cotizacionCliente);
        if (! $cotizacionCliente->esEditable()) {
            return redirect()
                ->route('cotizaciones-cliente.show', $cotizacionCliente)
                ->with('error', 'La versión cerrada o anulada ya no puede editarse.');
        }

        if ($cotizacionCliente->detalles()->where('origen_costeo', true)->exists()) {
            return redirect()
                ->route('cotizaciones-cliente.show', $cotizacionCliente)
                ->with('error', 'Esta cotización histórica utiliza el costeo avanzado y es de solo lectura en la versión reducida.');
        }

        $datos = $request->validated();
        $detalles = $datos['detalles'];
        unset($datos['detalles']);

        $observacionesHeredadas = $cotizacionCliente->detalles()
            ->whereNotNull('observacion')
            ->pluck('observacion', 'producto_id');
        $detalles = collect($detalles)
            ->map(function (array $detalle) use ($observacionesHeredadas): array {
                $observacion = $observacionesHeredadas->get(
                    (int) $detalle['producto_id']
                );

                return $observacion === null
                    ? $detalle
                    : [...$detalle, 'observacion' => $observacion];
            })
            ->all();

        DB::transaction(function () use (
            $cotizacionCliente,
            $datos,
            $detalles
        ): void {
            [$cliente, $resultado, $margen] = $this->prepararCotizacion(
                $datos,
                $detalles
            );

            $cotizacionCliente->update([
                ...$datos,
                ...$resultado['totales'],
                'cliente_documento' => $cliente->documentoVisible(),
                'cliente_nombre' => $cliente->nombreVisible(),
                'margen_cliente_porcentaje' => $margen,
            ]);
            $cotizacionCliente->detalles()->delete();
            $cotizacionCliente->detalles()->createMany(
                collect($resultado['detalles'])
                    ->map(fn(array $detalle): array => [
                        ...$detalle,
                        'componente_id' => null,
                    ])->all()
            );
        });

        return redirect()
            ->route('cotizaciones-cliente.show', $cotizacionCliente)
            ->with('success', 'Cambios guardados en la versión abierta.');
    }

    public function sincronizarDesdeCosteo(
        CotizacionCliente $cotizacionCliente,
        SincronizarHojaCostosCotizacionService $sincronizador
    ): RedirectResponse {
        $resultado = $sincronizador->sincronizar($cotizacionCliente);

        return redirect()
            ->route('cotizaciones-cliente.show', $cotizacionCliente)
            ->with(
                'success',
                'Cotización comercial actualizada desde la hoja de costos: '
                    . $resultado['lineas'] . ' líneas y total '
                    . $cotizacionCliente->simboloMoneda() . ' '
                    . number_format((float) $resultado['total'], 2) . '.'
            );
    }

    public function cerrar(
        Request $request,
        CotizacionCliente $cotizacionCliente
    ): RedirectResponse {
        $this->asegurarCotizacionSimple($cotizacionCliente);
        $cotizacionCliente->load('detalles');

        if (! $cotizacionCliente->esEditable()) {
            return back()->with('error', 'Solo una versión abierta puede cerrarse.');
        }

        if (
            $cotizacionCliente->detalles->isEmpty()
            || $cotizacionCliente->detalles->contains(
                fn($detalle): bool => (float) $detalle->precio_unitario <= 0
            )
        ) {
            return back()->with(
                'error',
                'Guarda un precio mayor que cero para cada línea comercial antes de cerrar.'
            );
        }

        $cotizacionCliente->update([
            'estado' => 'CERRADA',
            'cerrado_por' => $request->user()->id,
            'cerrado_en' => now(),
        ]);

        return redirect()
            ->route('cotizaciones-cliente.show', $cotizacionCliente)
            ->with('success', 'Cotización cerrada y bloqueada. Ya puede generar su Orden de Venta.');
    }

    public function nuevaVersion(
        Request $request,
        CotizacionCliente $cotizacionCliente
    ): RedirectResponse {
        $this->asegurarCotizacionSimple($cotizacionCliente);
        if (
            $cotizacionCliente->detalles()->where('origen_costeo', true)->exists()
            || $cotizacionCliente->componentes()->exists()
            || $cotizacionCliente->presupuestos()->exists()
        ) {
            return back()->with(
                'error',
                'La cotización histórica con costeo avanzado es de solo lectura en la versión reducida.'
            );
        }

        if (! $cotizacionCliente->puedeCrearVersion()) {
            return back()->with(
                'error',
                'La nueva versión solo puede partir de una cotización cerrada o ya convertida.'
            );
        }

        $nueva = DB::transaction(function () use ($cotizacionCliente, $request): CotizacionCliente {
            $origen = CotizacionCliente::query()
                ->lockForUpdate()
                ->with([
                    'detalles',
                    'componentes',
                    'todasLasAreas',
                    'presupuestos' => fn($query) => $query->where('estado', 'VIGENTE'),
                ])
                ->findOrFail($cotizacionCliente->id);
            $familia = CotizacionCliente::query()
                ->where('codigo_base', $origen->codigo_base);
            $ultimaVersion = (int) (clone $familia)->max('version');

            if ($origen->version !== $ultimaVersion) {
                abort(422, 'Crea la nueva versión desde la versión más reciente.');
            }

            if ((clone $familia)->where('estado', 'ABIERTA')->exists()) {
                abort(422, 'Ya existe una versión abierta para esta cotización.');
            }

            $version = $ultimaVersion + 1;
            $nueva = $origen->replicate([
                'codigo',
                'version',
                'estado',
                'cerrado_por',
                'cerrado_en',
                'anulado_por',
                'anulado_en',
                'motivo_anulacion',
                'orden_operacion_id',
            ]);
            $nueva->version = $version;
            $nueva->codigo = $origen->codigo_base . '-VRS' . $version;
            $nueva->estado = 'ABIERTA';
            $nueva->cotizado_por = $request->user()->id;
            $nueva->fecha_emision = now()->toDateString();
            $nueva->save();

            $mapaComponentes = [];
            foreach ($origen->componentes as $componente) {
                $clonado = $nueva->componentes()->create([
                    ...$componente->only([
                        'tipo_orden_id',
                        'descripcion_componente',
                        'cliente_direccion_id',
                        'vehiculo_id',
                        'tipo_cambio_comparacion',
                        'orden_secuencia',
                    ]),
                    'orden_operacion_id' => null,
                ]);
                $mapaComponentes[$componente->id] = $clonado->id;
            }

            $areasOrigen = $origen->todasLasAreas->keyBy('id');
            $mapaAreas = [];
            $clonarArea = function ($area) use (
                &$clonarArea,
                &$mapaAreas,
                $areasOrigen,
                $mapaComponentes,
                $nueva
            ) {
                if (isset($mapaAreas[$area->id])) {
                    return $mapaAreas[$area->id];
                }

                $areaPadreId = null;
                if ($area->area_padre_id && $areasOrigen->has($area->area_padre_id)) {
                    $areaPadreId = $clonarArea($areasOrigen->get($area->area_padre_id));
                }

                $clonada = $nueva->todasLasAreas()->create([
                    'componente_origen_id' => $area->componente_origen_id
                        ? ($mapaComponentes[$area->componente_origen_id] ?? null)
                        : null,
                    'area_padre_id' => $areaPadreId,
                    'nombre' => $area->nombre,
                    'nombre_normalizado' => $area->nombre_normalizado,
                    'orden_secuencia' => $area->orden_secuencia,
                    'origen' => $area->origen,
                    'estado' => 'VIGENTE',
                ]);
                $mapaAreas[$area->id] = $clonada->id;

                return $clonada->id;
            };
            $areasOrigen->each($clonarArea);

            $nueva->detalles()->createMany(
                $origen->detalles->map(fn($detalle): array => [
                    'componente_id' => $detalle->componente_id
                        ? ($mapaComponentes[$detalle->componente_id] ?? null)
                        : null,
                    ...$detalle->only([
                        'proforma_detalle_id',
                        'producto_id',
                        'tipo_linea',
                        'origen_costeo',
                        'codigo_producto',
                        'descripcion',
                        'unidad_medida',
                        'cantidad',
                        'costo_referencia',
                        'margen_sugerido',
                        'precio_sugerido',
                        'precio_unitario',
                        'igv_modo',
                        'igv_porcentaje',
                        'subtotal',
                        'impuesto',
                        'total',
                        'observacion',
                    ]),
                ])->all()
            );

            $nueva->presupuestos()->createMany(
                $origen->presupuestos->map(fn($partida): array => [
                    'componente_id' => $partida->componente_id
                        ? ($mapaComponentes[$partida->componente_id] ?? null)
                        : null,
                    'cotizacion_area_id' => $partida->cotizacion_area_id
                        ? ($mapaAreas[$partida->cotizacion_area_id] ?? null)
                        : null,
                    ...$partida->only([
                        'producto_id',
                        'tipo_costo',
                        'ejecucion_servicio',
                        'grupo_costo',
                        'descripcion',
                        'cantidad',
                        'unidad',
                        'moneda',
                        'tipo_cambio',
                        'costo_unitario',
                        'margen_porcentaje',
                        'carga_social_porcentaje',
                        'carga_social_original',
                        'igv_modo',
                        'igv_porcentaje',
                        'igv_venta_porcentaje',
                        'costo_neto_original',
                        'igv_original',
                        'costo_total_original',
                        'costo_neto_soles',
                        'igv_soles',
                        'costo_total_soles',
                        'costo_neto_dolares',
                        'igv_dolares',
                        'costo_total_dolares',
                        'precio_venta_neto_original',
                        'igv_venta_original',
                        'precio_venta_total_original',
                        'utilidad_estimada_original',
                        'igv_por_pagar_original',
                        'precio_venta_neto_soles',
                        'igv_venta_soles',
                        'precio_venta_total_soles',
                        'utilidad_estimada_soles',
                        'igv_por_pagar_soles',
                        'precio_venta_neto_dolares',
                        'igv_venta_dolares',
                        'precio_venta_total_dolares',
                        'utilidad_estimada_dolares',
                        'igv_por_pagar_dolares',
                        'observacion',
                    ]),
                    'estado' => 'VIGENTE',
                    'registrado_por' => $request->user()->id,
                    'registrado_en' => now(),
                ])->all()
            );

            return $nueva;
        });

        return redirect()
            ->route('cotizaciones-cliente.edit', $nueva)
            ->with('success', "Versión VRS{$nueva->version} creada como abierta.");
    }

    public function convertirEnOrden(
        Request $request,
        CotizacionCliente $cotizacionCliente,
        ConvertirCotizacionEnOrdenVentaService $conversion
    ): RedirectResponse {
        $this->asegurarCotizacionSimple($cotizacionCliente);
        if (
            $cotizacionCliente->detalles()->where('origen_costeo', true)->exists()
            || $cotizacionCliente->componentes()->exists()
            || $cotizacionCliente->presupuestos()->exists()
        ) {
            return back()->with(
                'error',
                'La cotización histórica con costeo avanzado no puede generar una orden en la versión reducida.'
            );
        }

        $datos = $request->validate([
            'fecha_apertura' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $orden = $conversion->convertir(
            $cotizacionCliente,
            $datos['fecha_apertura'],
            $request->user()
        );

        return redirect()
            ->route('cotizaciones-cliente.show', $cotizacionCliente)
            ->with('success', "Cotización {$cotizacionCliente->codigo} convertida en Orden de Venta {$orden->codigo_orden}.");
    }

    public function anular(
        AnularDocumentoComercialRequest $request,
        CotizacionCliente $cotizacionCliente
    ): RedirectResponse {
        $this->asegurarCotizacionSimple($cotizacionCliente);
        if ($cotizacionCliente->estado === 'ANULADA') {
            return back()->with('error', 'Esta versión ya se encuentra anulada.');
        }

        if ($cotizacionCliente->estado === 'CONVERTIDA_EN_ORDEN') {
            return back()->with('error', 'Una versión utilizada en una orden no puede anularse.');
        }

        DB::transaction(function () use ($cotizacionCliente, $request): void {
            $cotizacionCliente->update([
                'estado' => 'ANULADA',
                'anulado_por' => $request->user()->id,
                'anulado_en' => now(),
                'motivo_anulacion' => $request->validated('motivo_anulacion'),
            ]);

            $proforma = $cotizacionCliente->proforma;

            if (! $proforma) {
                return;
            }

            $vigentes = $proforma->cotizacionesCliente()
                ->whereIn('estado', ['ABIERTA', 'CERRADA', 'CONVERTIDA_EN_ORDEN'])
                ->exists();

            if (! $vigentes) {
                $proforma->update(['estado' => 'ENVIADA_A_LOGISTICA']);
            }
        });

        return redirect()
            ->route('cotizaciones-cliente.show', $cotizacionCliente)
            ->with('success', 'Versión anulada. Se conserva completa en el historial.');
    }

    private function prepararCotizacion(array $datos, array $detalles): array
    {
        $cliente = Cliente::query()
            ->with('tipoCliente')
            ->findOrFail($datos['cliente_id']);
        $productos = Producto::query()
            ->with(['unidadMedida', 'inventarios'])
            ->whereIn('id', collect($detalles)->pluck('producto_id'))
            ->get()
            ->keyBy('id');
        $margen = (float) ($cliente->tipoCliente?->porcentaje_ganancia ?? 0);
        $lineasEntrada = collect($detalles)->map(
            function (array $detalle) use ($productos, $margen, $datos): array {
                $producto = $productos->get((int) $detalle['producto_id']);
                abort_unless($producto, 422, 'Uno de los productos ya no está disponible.');
                $unidad = $producto->unidadMedida;
                $costoPen = $producto->costoPromedioActual();
                $costo = $datos['moneda'] === 'USD'
                    ? round($costoPen / (float) $datos['tipo_cambio'], 4)
                    : $costoPen;

                return [
                    'producto_id' => $producto->id,
                    'codigo_producto' => $producto->codigo,
                    'descripcion' => $producto->descripcion,
                    'unidad_medida' => $unidad?->abreviatura
                        ?? $unidad?->codigo
                        ?? $unidad?->nombre,
                    'cantidad' => $detalle['cantidad'],
                    'costo_referencia' => $costo,
                    'margen_sugerido' => $margen,
                    'precio_unitario' => $detalle['precio_unitario'],
                    'igv_modo' => $detalle['igv_modo'],
                    'observacion' => $detalle['observacion'] ?? null,
                    'componente_id' => $detalle['componente_id'] ?? null,
                ];
            }
        )->all();

        return [
            $cliente,
            $this->calculador->calcular($lineasEntrada),
            $margen,
        ];
    }

    private function catalogos(
        Request $request,
        ?CotizacionCliente $cotizacion = null
    ): array {
        $detallesOld = $request->old('detalles');
        $detallesBase = $cotizacion?->detalles ?? collect();
        $productoIds = collect(is_array($detallesOld) ? $detallesOld : $detallesBase)
            ->map(fn($detalle) => is_array($detalle)
                ? ($detalle['producto_id'] ?? null)
                : $detalle->producto_id)
            ->filter()
            ->unique()
            ->all();
        $clienteId = (int) $request->old(
            'cliente_id',
            $cotizacion?->cliente_id
        );
        $direccionId = (int) $request->old(
            'cliente_direccion_id',
            $cotizacion?->cliente_direccion_id
        );
        return [
            'clienteSeleccionado' => Cliente::query()
                ->with('tipoCliente')
                ->find($clienteId),
            'productosSeleccionados' => Producto::query()
                ->with(['unidadMedida', 'inventarios'])
                ->whereIn('id', $productoIds)
                ->get()
                ->keyBy('id'),
            'tiposCotizacion' => TipoOrden::query()
                ->where('estado', true)
                ->where('codigo', 'OV')
                ->orderBy('codigo')
                ->get(),
            'direcciones' => ClienteDireccion::query()
                ->when(
                    $clienteId,
                    fn($query) => $query->where('cliente_id', $clienteId),
                    fn($query) => $query->whereRaw('1 = 0')
                )
                ->where(function ($query) use ($direccionId): void {
                    $query->where('estado', true);

                    if ($direccionId) {
                        $query->orWhere('id', $direccionId);
                    }
                })
                ->orderByDesc('es_fiscal')
                ->orderByDesc('es_principal')
                ->orderBy('destino')
                ->get(),
            'vehiculos' => collect(),
        ];
    }

    private function asegurarCotizacionSimple(CotizacionCliente $cotizacion): void
    {
        abort_if($cotizacion->proforma_id !== null, 404);
    }

    private function siguienteCodigoBase(): string
    {
        $ultimo = CotizacionCliente::query()
            ->where('codigo_base', 'like', 'COT-%')
            ->lockForUpdate()
            ->latest('id')
            ->value('codigo_base');
        $secuencia = is_string($ultimo)
            && preg_match('/^COT-(\d{6})$/', $ultimo, $coincidencias)
            ? (int) $coincidencias[1] + 1
            : 1;

        do {
            $codigo = sprintf('COT-%06d', $secuencia++);
        } while (
            CotizacionCliente::query()
            ->where('codigo_base', $codigo)
            ->exists()
        );

        return $codigo;
    }
}
