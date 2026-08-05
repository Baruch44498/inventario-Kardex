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
use App\Services\Ventas\CalcularProformaService;
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
            ->with(['cliente.tipoCliente', 'proforma', 'ordenOperacion', 'cotizador'])
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
        $detalles = $datos['detalles'];
        unset($datos['detalles']);

        $cotizacion = DB::transaction(function () use ($datos, $detalles, $request): CotizacionCliente {
            [$cliente, $resultado, $margen] = $this->prepararCotizacion(
                $datos,
                $detalles
            );
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
            $cotizacion->detalles()->createMany($resultado['detalles']);

            return $cotizacion;
        });

        return redirect()
            ->route('cotizaciones-cliente.show', $cotizacion)
            ->with('success', 'Cotización directa creada como VRS1 abierta.');
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
            $tipoVenta = TipoOrden::query()
                ->where('codigo', 'OV')
                ->where('estado', true)
                ->firstOrFail();
            $margen = (float) ($cliente->tipoCliente?->porcentaje_ganancia ?? 0);
            $entradas = $proformaBloqueada->detalles->map(
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
                'tipo_orden_id' => $tipoVenta->id,
                'cliente_direccion_id' => null,
                'vehiculo_id' => null,
                'descripcion_trabajo' => 'Venta directa de productos según '
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
            'detalles.producto',
        ]);
        $versiones = CotizacionCliente::query()
            ->where('codigo_base', $cotizacionCliente->codigo_base)
            ->orderBy('version')
            ->get();
        $tiposOrden = TipoOrden::query()
            ->where('estado', true)
            ->when(
                $cotizacionCliente->proforma_id !== null,
                fn($query) => $query->where('codigo', 'OV'),
                fn($query) => $query->whereIn('codigo', ['OM', 'OS', 'OP'])
            )
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

        DB::transaction(function () use ($cotizacionCliente, $datos, $detalles): void {
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
            $cotizacionCliente->detalles()->createMany($resultado['detalles']);
        });

        return redirect()
            ->route('cotizaciones-cliente.show', $cotizacionCliente)
            ->with('success', 'Cambios guardados en la versión abierta.');
    }

    public function cerrar(Request $request, CotizacionCliente $cotizacionCliente): RedirectResponse
    {
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
                'Guarda un precio mayor que cero para cada producto antes de cerrar.'
            );
        }

        $cotizacionCliente->update([
            'estado' => 'CERRADA',
            'cerrado_por' => $request->user()->id,
            'cerrado_en' => now(),
        ]);

        return redirect()
            ->route('cotizaciones-cliente.show', $cotizacionCliente)
            ->with('success', 'Cotización cerrada y bloqueada. Ya puede convertirse en orden.');
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
                ->with('detalles')
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

            $nueva->detalles()->createMany(
                $origen->detalles->map(fn($detalle): array => [
                    ...$detalle->only([
                        'proforma_detalle_id',
                        'producto_id',
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

            return $nueva;
        });

        return redirect()
            ->route('cotizaciones-cliente.edit', $nueva)
            ->with('success', "Versión VRS{$nueva->version} creada como abierta.");
    }

    public function convertirEnOrden(
        Request $request,
        CotizacionCliente $cotizacionCliente
    ): RedirectResponse {
        $datos = $request->validate([
            'tipo_orden_id' => [
                'nullable',
                'integer',
                Rule::exists('tipos_orden', 'id')->where('estado', true),
            ],
            'fecha_apertura' => ['required', 'date', 'before_or_equal:today'],
            'descripcion' => ['nullable', 'string', 'min:5', 'max:500'],
            'cliente_direccion_id' => [
                'nullable',
                'integer',
                'exists:cliente_direcciones,id',
            ],
            'vehiculo_id' => ['nullable', 'integer', 'exists:vehiculos,id'],
        ]);

        if (
            $cotizacionCliente->tipo_orden_id
            && isset($datos['tipo_orden_id'])
            && (int) $datos['tipo_orden_id'] !== (int) $cotizacionCliente->tipo_orden_id
        ) {
            return back()->with(
                'error',
                'El tipo de orden ya fue definido dentro de la cotización.'
            );
        }

        $orden = DB::transaction(function () use (
            $cotizacionCliente,
            $datos,
            $request
        ): OrdenOperacion {
            $cotizacion = CotizacionCliente::query()
                ->with(['proforma', 'detalles', 'tipoOrden'])
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
                abort(422, 'Completa los productos y precios antes de aprobar la cotización.');
            }

            $tipoId = $cotizacion->tipo_orden_id
                ?: ($datos['tipo_orden_id'] ?? null);

            $tipo = $tipoId
                ? TipoOrden::query()->lockForUpdate()->find($tipoId)
                : null;
            $descripcion = trim((string) (
                $cotizacion->descripcion_trabajo
                ?: ($datos['descripcion'] ?? '')
            ));
            $direccionId = $cotizacion->cliente_direccion_id
                ?: ($datos['cliente_direccion_id'] ?? null);
            $vehiculoId = $cotizacion->vehiculo_id
                ?: ($datos['vehiculo_id'] ?? null);

            abort_unless(
                $tipo && $descripcion !== '',
                422,
                'Completa el tipo y la descripción del trabajo antes de generar la orden.'
            );

            if ($cotizacion->proforma_id !== null && $tipo->codigo !== 'OV') {
                abort(422, 'Una venta directa de Almacén solo puede generar una OV.');
            }

            if (
                $cotizacion->proforma_id === null
                && ! in_array($tipo->codigo, ['OM', 'OS', 'OP'], true)
            ) {
                abort(422, 'Comercial solo puede generar OM, OS u OP desde esta cotización.');
            }

            if ($tipo->codigo === 'OM' && ! $vehiculoId) {
                abort(422, 'La orden de mantenimiento necesita un vehículo asociado.');
            }

            if (in_array($tipo->codigo, ['OP', 'OV'], true) && $vehiculoId) {
                abort(422, 'Este tipo de orden no debe relacionarse con un vehículo existente.');
            }

            if ($direccionId) {
                $direccionValida = ClienteDireccion::query()
                    ->whereKey($direccionId)
                    ->where('cliente_id', $cotizacion->cliente_id)
                    ->where('estado', true)
                    ->exists();
                abort_unless(
                    $direccionValida,
                    422,
                    'La ubicación no pertenece al cliente de la cotización.'
                );
            }

            if ($vehiculoId) {
                $vehiculo = Vehiculo::query()->whereKey($vehiculoId)->first();
                abort_unless(
                    $vehiculo
                        && $vehiculo->estado
                        && ($vehiculo->cliente_id === null
                            || (int) $vehiculo->cliente_id === (int) $cotizacion->cliente_id),
                    422,
                    'El vehículo no pertenece al cliente de la cotización.'
                );
            }

            $anio = (int) date('Y', strtotime($datos['fecha_apertura']));
            $ultimo = OrdenOperacion::query()
                ->where('tipo_orden_id', $tipo->id)
                ->where('anio', $anio)
                ->lockForUpdate()
                ->max('numero_correlativo');
            $correlativo = ((int) $ultimo) + 1;
            $codigo = sprintf('%s-%03d-%02d', $tipo->codigo, $correlativo, $anio % 100);

            $orden = OrdenOperacion::query()->create([
                'tipo_orden_id' => $tipo->id,
                'cliente_id' => $cotizacion->cliente_id,
                'cliente_direccion_id' => $direccionId,
                'vehiculo_id' => $vehiculoId,
                'codigo_orden' => $codigo,
                'numero_correlativo' => $correlativo,
                'anio' => $anio,
                'fecha_apertura' => $datos['fecha_apertura'],
                'descripcion' => $descripcion,
                'estado' => 'ABIERTA',
                'creado_por' => $request->user()->id,
            ]);

            $cotizacion->update([
                'estado' => 'CONVERTIDA_EN_ORDEN',
                'orden_operacion_id' => $orden->id,
                'tipo_orden_id' => $tipo->id,
                'cliente_direccion_id' => $direccionId,
                'vehiculo_id' => $vehiculoId,
                'descripcion_trabajo' => $descripcion,
                'cerrado_por' => $cotizacion->cerrado_por ?: $request->user()->id,
                'cerrado_en' => $cotizacion->cerrado_en ?: now(),
            ]);

            if ($cotizacion->proforma) {
                $cotizacion->proforma->update([
                    'estado' => 'CONVERTIDA_EN_ORDEN',
                ]);
            }

            return $orden;
        });

        return redirect()
            ->route('ordenes-operacion.show', $orden)
            ->with(
                'success',
                "Cotización {$cotizacionCliente->codigo} aprobada y convertida en {$orden->codigo_orden}."
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
                    ? ['OV']
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
}
