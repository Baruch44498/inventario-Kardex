<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularDocumentoComercialRequest;
use App\Http\Requests\GuardarCotizacionClienteRequest;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\CotizacionCliente;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\TipoOrden;
use App\Models\Vehiculo;
use App\Services\Ordenes\MaterialRequeridoOrdenService;
use App\Services\Ventas\CalcularProformaService;
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
            'origen' => ['nullable', Rule::in(CotizacionCliente::ORIGENES)],
            'estado' => ['nullable', Rule::in(CotizacionCliente::ESTADOS)],
        ]);

        $consulta = CotizacionCliente::query()
            ->with([
                'cliente.tipoCliente',
                'proforma',
                'ordenOperacion',
                'ordenesOperacion',
                'componentes.tipoOrden',
                'cotizador',
            ])
            ->withCount('detalles');

        if (! empty($filtros['q'])) {
            $termino = trim($filtros['q']);

            $consulta->where(function ($query) use ($termino): void {
                $query->where('codigo', 'like', "%{$termino}%")
                    ->orWhere('codigo_base', 'like', "%{$termino}%")
                    ->orWhere('cliente_documento', 'like', "%{$termino}%")
                    ->orWhere('cliente_nombre', 'like', "%{$termino}%")
                    ->orWhereHas(
                        'proforma',
                        fn($proforma) => $proforma->where('codigo', 'like', "%{$termino}%")
                    )
                    ->orWhereHas(
                        'ordenOperacion',
                        fn($orden) => $orden->where('codigo_orden', 'like', "%{$termino}%")
                    )
                    ->orWhereHas(
                        'ordenesOperacion',
                        fn($orden) => $orden->where('codigo_orden', 'like', "%{$termino}%")
                    );
            });
        }

        foreach (['origen', 'estado'] as $campo) {
            if (! empty($filtros[$campo])) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }

        $cotizaciones = $consulta
            ->latest('fecha_emision')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $resumen = [
            'abiertas' => CotizacionCliente::query()->where('estado', 'ABIERTA')->count(),
            'cerradas' => CotizacionCliente::query()->where('estado', 'CERRADA')->count(),
            'ordenes' => CotizacionCliente::query()
                ->where('estado', 'CONVERTIDA_EN_ORDEN')
                ->count(),
            'anuladas' => CotizacionCliente::query()->where('estado', 'ANULADA')->count(),
        ];

        return view(
            'cotizaciones_cliente.index',
            compact('cotizaciones', 'resumen')
        );
    }

    public function create(Request $request): View
    {
        $cotizacion = new CotizacionCliente([
            'fecha_emision' => today(),
            'moneda' => 'PEN',
            'estado' => 'ABIERTA',
            'origen' => 'DIRECTA_LOGISTICA',
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
        $detalles = $datos['detalles'] ?? [];
        $tipoCambioComparacion = $datos['tipo_cambio_comparacion'] ?? null;
        unset($datos['detalles'], $datos['tipo_cambio_comparacion']);
        $creadaDesdeEstructura = $detalles === [];

        $cotizacion = DB::transaction(function () use (
            $datos,
            $detalles,
            $tipoCambioComparacion,
            $request
        ): CotizacionCliente {
            $cliente = Cliente::query()
                ->with('tipoCliente')
                ->findOrFail($datos['cliente_id']);
            $margen = (float) ($cliente->tipoCliente?->porcentaje_ganancia ?? 0);
            $resultado = $detalles === []
                ? [
                    'totales' => ['subtotal' => 0, 'impuesto' => 0, 'total' => 0],
                    'detalles' => [],
                ]
                : $this->prepararCotizacion($datos, $detalles)[1];
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
            $componente = $this->crearComponentePrincipal(
                $cotizacion,
                $tipoCambioComparacion
            );
            if ($resultado['detalles'] !== []) {
                $cotizacion->detalles()->createMany(
                    collect($resultado['detalles'])
                        ->map(fn(array $detalle): array => [
                            ...$detalle,
                            'componente_id' => $componente->id,
                        ])->all()
                );
            }

            return $cotizacion;
        });

        return redirect()
            ->route(
                $creadaDesdeEstructura
                    ? 'cotizaciones-cliente.componentes.show'
                    : 'cotizaciones-cliente.show',
                $cotizacion
            )
            ->with(
                'success',
                $creadaDesdeEstructura
                    ? 'Cotización creada. Revisa el primer trabajo, agrega otros componentes si corresponde y luego carga sus costos.'
                    : 'Cotización directa creada como VRS1 abierta.'
            );
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
        $cotizacionCliente->load([
            'proforma',
            'cliente.tipoCliente',
            'tipoOrden',
            'clienteDireccion',
            'vehiculo',
            'cotizador',
            'cerrador',
            'anulador',
            'ordenOperacion.tipoOrden',
            'ordenesOperacion.tipoOrden',
            'componentes.tipoOrden',
            'componentes.ordenOperacion',
            'detalles.producto',
            'detalles.componente.tipoOrden',
        ]);
        $versiones = CotizacionCliente::query()
            ->where('codigo_base', $cotizacionCliente->codigo_base)
            ->orderBy('version')
            ->get();
        $tiposOrden = $cotizacionCliente->proforma_id !== null
            ? collect()
            : TipoOrden::query()
            ->where('estado', true)
            ->whereIn('codigo', ['OM', 'OS', 'OP'])
            ->orderBy('codigo')
            ->get();
        $direccionesCliente = ClienteDireccion::query()
            ->where('cliente_id', $cotizacionCliente->cliente_id)
            ->where('estado', true)
            ->orderByDesc('es_fiscal')
            ->orderByDesc('es_principal')
            ->orderBy('destino')
            ->get();
        $vehiculosCliente = Vehiculo::query()
            ->where('estado', true)
            ->where(function ($query) use ($cotizacionCliente): void {
                $query->where('cliente_id', $cotizacionCliente->cliente_id)
                    ->orWhereNull('cliente_id');
            })
            ->orderBy('placa')
            ->get();

        return view('cotizaciones_cliente.show', [
            'cotizacion' => $cotizacionCliente,
            'versiones' => $versiones,
            'tiposOrden' => $tiposOrden,
            'direccionesCliente' => $direccionesCliente,
            'vehiculosCliente' => $vehiculosCliente,
        ]);
    }

    public function edit(
        Request $request,
        CotizacionCliente $cotizacionCliente
    ): View|RedirectResponse {
        $cotizacionCliente->load('detalles');

        if (! $cotizacionCliente->esEditable()) {
            return redirect()
                ->route('cotizaciones-cliente.show', $cotizacionCliente)
                ->with('error', 'La versión cerrada o anulada ya no puede editarse.');
        }

        if (
            $cotizacionCliente->proforma_id === null
            && $cotizacionCliente->detalles->isEmpty()
        ) {
            return redirect()
                ->route('cotizaciones-cliente.componentes.show', $cotizacionCliente)
                ->with('error', 'Primero completa los componentes y su hoja de costos.');
        }

        if ($cotizacionCliente->detalles->contains('origen_costeo', true)) {
            return redirect()
                ->route('cotizaciones-cliente.presupuesto.show', $cotizacionCliente)
                ->with('error', 'Esta cotización se valoriza desde su hoja de costos. Modifica el costeo y vuelve a sincronizar.');
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
        if (! $cotizacionCliente->esEditable()) {
            return redirect()
                ->route('cotizaciones-cliente.show', $cotizacionCliente)
                ->with('error', 'La versión cerrada o anulada ya no puede editarse.');
        }

        if ($cotizacionCliente->detalles()->where('origen_costeo', true)->exists()) {
            return redirect()
                ->route('cotizaciones-cliente.presupuesto.show', $cotizacionCliente)
                ->with('error', 'Esta cotización se valoriza desde su hoja de costos. Modifica el costeo y vuelve a sincronizar.');
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

        $componentesHeredados = $cotizacionCliente->detalles()
            ->pluck('componente_id', 'producto_id');

        DB::transaction(function () use (
            $cotizacionCliente,
            $datos,
            $detalles,
            $componentesHeredados
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
            $principal = $cotizacionCliente->proforma_id === null
                ? $this->sincronizarComponentePrincipal($cotizacionCliente)
                : null;
            $cotizacionCliente->detalles()->delete();
            $cotizacionCliente->detalles()->createMany(
                collect($resultado['detalles'])
                    ->map(function (array $detalle) use (
                        $componentesHeredados,
                        $principal
                    ): array {
                        return [
                            ...$detalle,
                            'componente_id' => $componentesHeredados->get(
                                (int) $detalle['producto_id']
                            ) ?: $principal?->id,
                        ];
                    })->all()
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

    public function cerrar(Request $request, CotizacionCliente $cotizacionCliente): RedirectResponse
    {
        $cotizacionCliente->load([
            'detalles',
            'componentes.tipoOrden',
            'presupuestos' => fn($query) => $query->where('estado', 'VIGENTE'),
        ]);

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

        if ($cotizacionCliente->proforma_id === null) {
            if ($cotizacionCliente->componentes->isEmpty()) {
                return back()->with('error', 'Agrega al menos un componente operativo.');
            }
            if ($cotizacionCliente->detalles->contains(fn($detalle) => ! $detalle->componente_id)) {
                return back()->with('error', 'Asigna todas las líneas comerciales a sus componentes antes de cerrar.');
            }
            $componentesSinProductos = $cotizacionCliente->componentes
                ->reject(fn($componente) => $cotizacionCliente->detalles->contains(
                    'componente_id',
                    $componente->id
                ))
                ->pluck('orden_secuencia')
                ->implode(', ');
            if ($componentesSinProductos !== '') {
                return back()->with(
                    'error',
                    'Cada componente debe tener al menos una línea comercial. '
                        . 'Revisa los componentes: ' . $componentesSinProductos . '.'
                );
            }
            if ($cotizacionCliente->presupuestos->contains(fn($partida) => ! $partida->componente_id)) {
                return back()->with('error', 'Asigna todos los costos internos a sus componentes antes de cerrar.');
            }
            if (
                $cotizacionCliente->presupuestos->isNotEmpty()
                && $cotizacionCliente->costeo_sincronizado_en === null
            ) {
                return back()->with(
                    'error',
                    'La hoja de costos cambió. Sincronízala con la cotización antes de cerrar.'
                );
            }
            if ($cotizacionCliente->componentes->contains(
                fn($componente) => ! $componente->tipoOrden
                    || trim((string) $componente->descripcion_componente) === ''
                    || ($componente->tipoOrden?->codigo === 'OM'
                        && $componente->vehiculo_id === null)
                    || ($componente->tipoOrden?->codigo === 'OP'
                        && $componente->vehiculo_id !== null)
            )) {
                return back()->with(
                    'error',
                    'Completa el tipo, descripción y contexto operativo de cada componente.'
                );
            }
        }

        $cotizacionCliente->update([
            'estado' => 'CERRADA',
            'cerrado_por' => $request->user()->id,
            'cerrado_en' => now(),
        ]);

        return redirect()
            ->route('cotizaciones-cliente.show', $cotizacionCliente)
            ->with(
                'success',
                $cotizacionCliente->proforma_id !== null
                    ? 'Cotización cerrada y bloqueada. La venta queda valorizada para su posterior cobro.'
                    : 'Cotización cerrada y bloqueada. Ya puede convertirse en orden.'
            );
    }

    public function nuevaVersion(
        Request $request,
        CotizacionCliente $cotizacionCliente
    ): RedirectResponse {
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
                    ...$partida->only([
                        'producto_id',
                        'tipo_costo',
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
        MaterialRequeridoOrdenService $materialesRequeridos
    ): RedirectResponse {
        if ($cotizacionCliente->proforma_id !== null) {
            return back()->with(
                'error',
                'Las Proformas de Almacén ya no generan Orden de Venta. La cotización solo valoriza los productos que deben cobrarse.'
            );
        }

        $datos = $request->validate([
            'fecha_apertura' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $ordenes = DB::transaction(function () use (
            $cotizacionCliente,
            $datos,
            $request,
            $materialesRequeridos
        ) {
            $cotizacion = CotizacionCliente::query()
                ->with([
                    'proforma',
                    'detalles',
                    'presupuestos' => fn($query) => $query->where('estado', 'VIGENTE'),
                    'componentes.tipoOrden',
                ])
                ->lockForUpdate()
                ->findOrFail($cotizacionCliente->id);

            if (! $cotizacion->puedeConvertirseEnOrden()) {
                abort(422, 'La cotización ya no puede generar una orden.');
            }

            if (
                $cotizacion->detalles->isEmpty()
                || $cotizacion->detalles->contains(
                    fn($detalle): bool => (float) $detalle->precio_unitario <= 0
                )
            ) {
                abort(422, 'Completa las líneas comerciales y sus precios antes de aprobar la cotización.');
            }

            abort_unless(
                $cotizacion->componentes->isNotEmpty(),
                422,
                'La cotización no tiene componentes operativos.'
            );

            abort_if(
                $cotizacion->detalles->contains(fn($detalle) => ! $detalle->componente_id),
                422,
                'Asigna todas las líneas comerciales a un componente antes de aprobar.'
            );
            $componentesSinProductos = $cotizacion->componentes
                ->reject(fn($componente) => $cotizacion->detalles->contains(
                    'componente_id',
                    $componente->id
                ))
                ->pluck('orden_secuencia')
                ->implode(', ');
            abort_if(
                $componentesSinProductos !== '',
                422,
                'Cada componente debe tener al menos una línea comercial. '
                    . 'Revisa los componentes: ' . $componentesSinProductos . '.'
            );
            abort_if(
                $cotizacion->presupuestos->contains(fn($partida) => ! $partida->componente_id),
                422,
                'Asigna todos los costos internos vigentes a un componente antes de aprobar.'
            );
            abort_if(
                $cotizacion->presupuestos->isNotEmpty()
                    && $cotizacion->costeo_sincronizado_en === null,
                422,
                'La hoja de costos cambió. Sincronízala con la cotización antes de aprobar.'
            );

            $anio = (int) date('Y', strtotime($datos['fecha_apertura']));
            $ordenes = collect();

            foreach ($cotizacion->componentes as $componente) {
                $tipo = $componente->tipoOrden;
                $descripcion = trim((string) $componente->descripcion_componente);
                abort_unless(
                    $tipo && in_array($tipo->codigo, ['OM', 'OS', 'OP'], true)
                        && $descripcion !== '',
                    422,
                    'Todos los componentes necesitan tipo y descripción.'
                );
                abort_if($componente->orden_operacion_id, 422, 'Un componente ya generó su orden.');
                abort_if(
                    $tipo->codigo === 'OM' && ! $componente->vehiculo_id,
                    422,
                    'Cada componente de mantenimiento necesita un vehículo.'
                );
                abort_if(
                    $tipo->codigo === 'OP' && $componente->vehiculo_id,
                    422,
                    'Un componente de producción no admite vehículo existente.'
                );
                if ($componente->cliente_direccion_id) {
                    abort_unless(
                        ClienteDireccion::query()
                            ->whereKey($componente->cliente_direccion_id)
                            ->where('cliente_id', $cotizacion->cliente_id)
                            ->where('estado', true)
                            ->exists(),
                        422,
                        'Una ubicación de componente ya no pertenece al cliente.'
                    );
                }
                if ($componente->vehiculo_id) {
                    $vehiculo = Vehiculo::query()
                        ->whereKey($componente->vehiculo_id)
                        ->first();
                    abort_unless(
                        $vehiculo && $vehiculo->estado
                            && ($vehiculo->cliente_id === null
                                || (int) $vehiculo->cliente_id === (int) $cotizacion->cliente_id),
                        422,
                        'Un vehículo de componente ya no pertenece al cliente.'
                    );
                }

                $ultimo = OrdenOperacion::query()
                    ->where('tipo_orden_id', $tipo->id)
                    ->where('anio', $anio)
                    ->lockForUpdate()
                    ->max('numero_correlativo');
                $correlativo = ((int) $ultimo) + 1;
                $codigo = sprintf('%s-%03d-%02d', $tipo->codigo, $correlativo, $anio % 100);

                $orden = OrdenOperacion::query()->create([
                    'tipo_orden_id' => $tipo->id,
                    'cotizacion_cliente_id' => $cotizacion->id,
                    'cliente_id' => $cotizacion->cliente_id,
                    'cliente_direccion_id' => $componente->cliente_direccion_id,
                    'vehiculo_id' => $componente->vehiculo_id,
                    'codigo_orden' => $codigo,
                    'numero_correlativo' => $correlativo,
                    'anio' => $anio,
                    'fecha_apertura' => $datos['fecha_apertura'],
                    'descripcion' => $descripcion,
                    'estado' => 'ABIERTA',
                    'creado_por' => $request->user()->id,
                ]);

                $lineasComerciales = $cotizacion->detalles
                    ->where('componente_id', $componente->id);
                $usaHojaCostos = $lineasComerciales->contains('origen_costeo', true);
                $materialesIniciales = ($usaHojaCostos
                    ? $cotizacion->presupuestos
                    ->where('componente_id', $componente->id)
                    ->where('tipo_costo', 'MATERIAL')
                    ->whereNotNull('producto_id')
                    : $lineasComerciales->whereNotNull('producto_id'))
                    ->groupBy('producto_id')
                    ->map(fn($lineas): float => round((float) $lineas->sum('cantidad'), 3));

                foreach ($materialesIniciales as $productoId => $cantidadInicial) {
                    if ($cantidadInicial > 0) {
                        $materialesRequeridos->agregar(
                            $orden,
                            (int) $productoId,
                            $cantidadInicial,
                            "Componente {$componente->orden_secuencia} de {$cotizacion->codigo}.",
                            $request->user()
                        );
                    }
                }

                $componente->update(['orden_operacion_id' => $orden->id]);
                $ordenes->push($orden);
            }

            $principal = $cotizacion->componentes->first();
            $primeraOrden = $ordenes->first();

            $cotizacion->update([
                'estado' => 'CONVERTIDA_EN_ORDEN',
                'orden_operacion_id' => $primeraOrden?->id,
                'tipo_orden_id' => $principal?->tipo_orden_id,
                'cliente_direccion_id' => $principal?->cliente_direccion_id,
                'vehiculo_id' => $principal?->vehiculo_id,
                'descripcion_trabajo' => $principal?->descripcion_componente,
                'cerrado_por' => $cotizacion->cerrado_por ?: $request->user()->id,
                'cerrado_en' => $cotizacion->cerrado_en ?: now(),
            ]);

            if ($cotizacion->proforma) {
                $cotizacion->proforma->update([
                    'estado' => 'CONVERTIDA_EN_ORDEN',
                ]);
            }

            return $ordenes;
        });

        return redirect()
            ->route('cotizaciones-cliente.show', $cotizacionCliente)
            ->with(
                'success',
                "Cotización {$cotizacionCliente->codigo} aprobada: se generaron "
                    . $ordenes->count() . ' órdenes (' . $ordenes->pluck('codigo_orden')->implode(', ') . ').'
            );
    }

    public function anular(
        AnularDocumentoComercialRequest $request,
        CotizacionCliente $cotizacionCliente
    ): RedirectResponse {
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
        $vehiculoId = (int) $request->old(
            'vehiculo_id',
            $cotizacion?->vehiculo_id
        );
        $esVentaDirecta = $cotizacion?->proforma_id !== null;

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
                ->whereIn('codigo', $esVentaDirecta
                    ? []
                    : ['OM', 'OS', 'OP'])
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
            'vehiculos' => Vehiculo::query()
                ->where(function ($query) use ($clienteId, $vehiculoId): void {
                    $query->where(function ($disponibles) use ($clienteId): void {
                        $disponibles->where('estado', true);

                        if ($clienteId) {
                            $disponibles->where(function ($dueno) use ($clienteId): void {
                                $dueno->where('cliente_id', $clienteId)
                                    ->orWhereNull('cliente_id');
                            });
                        } else {
                            $disponibles->whereNull('cliente_id');
                        }
                    });

                    if ($vehiculoId) {
                        $query->orWhere('id', $vehiculoId);
                    }
                })
                ->orderBy('placa')
                ->get(),
        ];
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

    private function crearComponentePrincipal(
        CotizacionCliente $cotizacion,
        mixed $tipoCambioComparacion = null
    ) {
        return $cotizacion->componentes()->create([
            'tipo_orden_id' => $cotizacion->tipo_orden_id,
            'descripcion_componente' => $cotizacion->descripcion_trabajo,
            'cliente_direccion_id' => $cotizacion->cliente_direccion_id,
            'vehiculo_id' => $cotizacion->vehiculo_id,
            'tipo_cambio_comparacion' => $tipoCambioComparacion
                ?: $cotizacion->tipo_cambio,
            'orden_secuencia' => 1,
        ]);
    }

    private function sincronizarComponentePrincipal(CotizacionCliente $cotizacion)
    {
        $principal = $cotizacion->componentes()
            ->orderBy('orden_secuencia')
            ->first();

        if (! $principal) {
            return $this->crearComponentePrincipal($cotizacion);
        }

        $principal->update([
            'tipo_orden_id' => $cotizacion->tipo_orden_id,
            'descripcion_componente' => $cotizacion->descripcion_trabajo,
            'cliente_direccion_id' => $cotizacion->cliente_direccion_id,
            'vehiculo_id' => $cotizacion->vehiculo_id,
            'tipo_cambio_comparacion' => $principal->tipo_cambio_comparacion
                ?: $cotizacion->tipo_cambio,
        ]);

        return $principal;
    }
}
