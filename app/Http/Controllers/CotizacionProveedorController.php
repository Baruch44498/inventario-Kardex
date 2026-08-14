<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularCotizacionProveedorRequest;
use App\Http\Requests\ClasificarCotizacionProveedorRequest;
use App\Http\Requests\AprobarCompraYGenerarOrdenRequest;
use App\Http\Requests\StoreCotizacionProveedorRequest;
use App\Http\Requests\StoreProductoRapidoCotizacionRequest;
use App\Http\Requests\UpdateCotizacionProveedorRequest;
use App\Models\Cotizacion;
use App\Models\ImportacionCotizacionProveedor;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\UnidadMedida;
use App\Services\Compras\CalcularCotizacionProveedorService;
use App\Services\Compras\AprobarCompraYGenerarOrdenService;
use App\Services\Compras\ResolverVinculacionProductoCotizado;
use App\Services\Documentos\GenerarCodigoDocumentoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CotizacionProveedorController extends Controller
{
    private const AJUSTE_REDONDEO_MAXIMO = 0.05;

    public function __construct(
        private GenerarCodigoDocumentoService $codigos,
        private CalcularCotizacionProveedorService $calculador,
        private ResolverVinculacionProductoCotizado $vinculador,
        private AprobarCompraYGenerarOrdenService $aprobacionCompra
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'moneda' => ['nullable', 'in:PEN,USD'],
            'estado' => ['nullable', 'in:REGISTRADA,SELECCIONADA,NO_REQUERIDA,NO_UTILIZADA,ANULADA'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $query = Cotizacion::query()
            ->with(['proveedor', 'registrador'])
            ->withCount('detalles');

        if (! empty($filters['q'])) {
            $search = trim($filters['q']);

            $query->where(function ($q) use ($search): void {
                $q->where('codigo', 'like', "%{$search}%")
                    ->orWhere('numero_documento', 'like', "%{$search}%")
                    ->orWhereHas('proveedor', fn($provider) => $provider
                        ->where('razon_social', 'like', "%{$search}%")
                        ->orWhere('nombre_comercial', 'like', "%{$search}%")
                        ->orWhere('ruc', 'like', "%{$search}%"));
            });
        }

        foreach (['proveedor_id', 'moneda', 'estado'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['desde'])) {
            $query->whereDate('fecha_cotizacion', '>=', $filters['desde']);
        }

        if (! empty($filters['hasta'])) {
            $query->whereDate('fecha_cotizacion', '<=', $filters['hasta']);
        }

        $cotizaciones = $query
            ->latest('fecha_cotizacion')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $proveedorFiltro = ! empty($filters['proveedor_id'])
            ? Proveedor::query()->find($filters['proveedor_id'])
            : null;

        $resumen = [
            'total' => Cotizacion::query()->count(),
            'disponibles' => Cotizacion::query()->disponiblesParaCompra()->count(),
            'este_mes' => Cotizacion::query()
                ->whereYear('fecha_cotizacion', now()->year)
                ->whereMonth('fecha_cotizacion', now()->month)
                ->where('estado', '!=', 'ANULADA')
                ->count(),
            'proveedores' => Cotizacion::query()
                ->where('estado', '!=', 'ANULADA')
                ->distinct('proveedor_id')
                ->count('proveedor_id'),
        ];

        return view('cotizaciones_proveedor.index', compact(
            'cotizaciones',
            'proveedorFiltro',
            'resumen'
        ));
    }

    public function create(Request $request): View
    {
        $requisicionId = (int) $request->old(
            'requisicion_id',
            $request->integer('requisicion_id') ?: null
        ) ?: null;

        $detalleIds = collect($request->input('detalle_ids', []))
            ->when(
                is_string($request->input('detalle_ids')),
                fn($ids) => collect(explode(',', (string) $request->input('detalle_ids')))
            )
            ->filter(fn($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $importacionAsistida = null;
        $importacionId = (int) $request->old('importacion_cotizacion_id', $request->integer('importacion_id')) ?: null;
        if ($importacionId) {
            $importacionAsistida = ImportacionCotizacionProveedor::query()
                ->whereKey($importacionId)
                ->where('estado', 'BORRADOR')
                ->first();

            if (! $importacionAsistida) {
                abort(404);
            }

            if (
                (int) $importacionAsistida->creado_por !== (int) $request->user()->id
                && ! $request->user()->esAdministrador()
            ) {
                abort(403);
            }
        }

        $borradoresImportacion = ImportacionCotizacionProveedor::query()
            ->with(['requisicion:id,codigo', 'proveedor:id,razon_social,nombre_comercial'])
            ->where('estado', 'BORRADOR')
            ->where('creado_por', $request->user()->id)
            ->latest('updated_at')
            ->limit(5)
            ->get();

        return view(
            'cotizaciones_proveedor.create',
            [
                ...$this->catalogos(
                    (int) $request->old(
                        'proveedor_id',
                        $request->integer('proveedor_id') ?: null
                    ) ?: null,
                    $this->productosDelFormulario($request),
                    $requisicionId,
                    $detalleIds
                ),
                'importacionAsistida' => $importacionAsistida,
                'borradoresImportacion' => $borradoresImportacion,
            ]
        );
    }

    public function store(
        StoreCotizacionProveedorRequest $request
    ): RedirectResponse {
        $data = $request->validated();
        $importacionId = ! empty($data['importacion_cotizacion_id'])
            ? (int) $data['importacion_cotizacion_id']
            : null;

        $details = $this->vincularDetallesRequisicion(
            $data['requisicion_id'] ?? null,
            $data['detalles']
        );
        unset($data['detalles'], $data['importacion_cotizacion_id']);

        $quote = $this->codigos->usarSiguiente(
            'cotizaciones',
            'CP',
            $data['fecha_cotizacion'],
            function (string $code) use (
                $data,
                $details,
                $request,
                $importacionId
            ): Cotizacion {
                return DB::transaction(function () use (
                    $code,
                    $data,
                    $details,
                    $request,
                    $importacionId
                ): Cotizacion {
                    $quoteData = $data;
                    $importacion = null;

                    if ($importacionId) {
                        $importacion = ImportacionCotizacionProveedor::query()
                            ->whereKey($importacionId)
                            ->lockForUpdate()
                            ->firstOrFail();

                        if (
                            (int) $importacion->creado_por !== (int) $request->user()->id
                            && ! $request->user()->esAdministrador()
                        ) {
                            abort(403);
                        }

                        if (! $importacion->esBorrador()) {
                            throw ValidationException::withMessages([
                                'importacion_cotizacion_id' => 'Este documento ya fue confirmado o descartado. No se creó otra cotización.',
                            ]);
                        }

                        $requisicionImportada = $importacion->requisicion_id
                            ? (int) $importacion->requisicion_id
                            : null;
                        $requisicionFormulario = ! empty($quoteData['requisicion_id'])
                            ? (int) $quoteData['requisicion_id']
                            : null;

                        if (
                            $requisicionImportada !== null
                            && $requisicionImportada !== $requisicionFormulario
                        ) {
                            throw ValidationException::withMessages([
                                'requisicion_id' => 'La importación pertenece a otro requerimiento.',
                            ]);
                        }

                        $quoteData['origen_registro'] = $importacion->tipo_archivo === 'PDF'
                            ? 'IMPORTADO_PDF'
                            : 'IMPORTADO_EXCEL';
                        $quoteData['archivo_original_nombre'] = $importacion->nombre_original;
                        $quoteData['archivo_original_path'] = $importacion->ruta_archivo;
                    } else {
                        $quoteData['origen_registro'] = 'MANUAL';
                    }

                    [$lines, $totals] = $this->calculador->calcular(
                        $details,
                        $quoteData['descuento_global_modo'],
                        $quoteData['descuento_global_tipo'],
                        (float) ($quoteData['descuento_global_valor'] ?? 0)
                    );

                    if ($importacion !== null) {
                        $totals = $this->conciliarImportesDocumento(
                            data_get($importacion->datos_extraidos, 'cabecera', []),
                            $quoteData,
                            $totals,
                            (bool) ($quoteData['ajuste_redondeo_confirmado'] ?? false),
                            (int) $request->user()->id
                        );
                    }

                    unset($quoteData['ajuste_redondeo_confirmado']);

                    $quote = Cotizacion::query()->create([
                        ...$quoteData,
                        ...$totals,
                        'codigo' => $code,
                        'estado' => 'REGISTRADA',
                        'registrado_por' => $request->user()->id,
                    ]);

                    $quote->detalles()->createMany($lines);

                    if ($importacion !== null) {
                        $importacion->update([
                            'cotizacion_id' => $quote->id,
                            'requisicion_id' => $quote->requisicion_id,
                            'proveedor_id' => $quote->proveedor_id,
                            'estado' => 'CONFIRMADA',
                            'confirmado_por' => $request->user()->id,
                            'confirmado_en' => now(),
                        ]);
                    }

                    return $quote;
                });
            }
        );

        return redirect()
            ->route('cotizaciones-proveedor.show', $quote->id)
            ->with('success', 'Cotización registrada y añadida al historial de precios.');
    }

    public function show(Cotizacion $cotizacion): View
    {
        $cotizacion->load([
            'proveedor',
            'requisicion',
            'registrador',
            'anulador',
            'confirmadorAjusteRedondeo',
            'solicitudCompra',
            'solicitudCompra.aprobador',
            'solicitudCompra.rechazador',
            'solicitudCompra.ordenCompra',
            'detalles.producto.unidadMedida',
            'detalles.requisicionDetalle.producto',
        ]);

        return view('cotizaciones_proveedor.show', compact('cotizacion'));
    }

    public function edit(
        Request $request,
        Cotizacion $cotizacion
    ): View|RedirectResponse {
        $cotizacion->load([
            'detalles.requisicionDetalle',
            'solicitudCompra',
            'importacionAsistida',
        ]);

        if (! $cotizacion->puedeEditar()) {
            return redirect()
                ->route('cotizaciones-proveedor.show', $cotizacion->id)
                ->with('error', 'La cotización anulada o utilizada no puede editarse.');
        }

        return view('cotizaciones_proveedor.edit', [
            'cotizacion' => $cotizacion,
            'cabeceraDocumentoGuardado' => $this->cabeceraDocumentoCotizacion($cotizacion),
            ...$this->catalogos(
                (int) $request->old('proveedor_id', $cotizacion->proveedor_id) ?: null,
                $this->productosDelFormulario(
                    $request,
                    $cotizacion->detalles->pluck('producto_id')->all()
                ),
                (int) $request->old('requisicion_id', $cotizacion->requisicion_id) ?: null
            ),
        ]);
    }

    public function update(
        UpdateCotizacionProveedorRequest $request,
        Cotizacion $cotizacion
    ): RedirectResponse {
        $cotizacion->load('solicitudCompra');

        if (! $cotizacion->puedeEditar()) {
            return redirect()
                ->route('cotizaciones-proveedor.show', $cotizacion->id)
                ->with('error', 'La cotización anulada o utilizada no puede editarse.');
        }

        $data = $request->validated();
        $ajusteConfirmado = (bool) ($data['ajuste_redondeo_confirmado'] ?? false);
        $details = $this->vincularDetallesRequisicion(
            $data['requisicion_id'] ?? null,
            $data['detalles']
        );
        unset(
            $data['detalles'],
            $data['importacion_cotizacion_id'],
            $data['ajuste_redondeo_confirmado']
        );

        DB::transaction(function () use (
            $cotizacion,
            $data,
            $details,
            $ajusteConfirmado,
            $request
        ): void {
            [$lines, $totals] = $this->calculador->calcular(
                $details,
                $data['descuento_global_modo'],
                $data['descuento_global_tipo'],
                (float) ($data['descuento_global_valor'] ?? 0)
            );

            $cabeceraDocumento = $this->cabeceraDocumentoCotizacion($cotizacion);
            if (is_numeric(data_get($cabeceraDocumento, 'importes_documento.total'))) {
                $totals = $this->conciliarImportesDocumento(
                    $cabeceraDocumento,
                    $data,
                    $totals,
                    $ajusteConfirmado,
                    (int) $request->user()->id,
                    $cotizacion
                );
            }

            $cotizacion->update([...$data, ...$totals]);
            $cotizacion->detalles()->delete();
            $cotizacion->detalles()->createMany($lines);
        });

        return redirect()
            ->route('cotizaciones-proveedor.show', $cotizacion->id)
            ->with('success', 'Cotización actualizada correctamente.');
    }

    public function anular(
        AnularCotizacionProveedorRequest $request,
        Cotizacion $cotizacion
    ): RedirectResponse {
        $cotizacion->load('solicitudCompra');

        if ($cotizacion->estaAnulada()) {
            return back()->with('error', 'La cotización ya se encuentra invalidada.');
        }

        if ($cotizacion->estaUtilizada()) {
            return back()->with(
                'error',
                'No se puede invalidar una cotización utilizada en una solicitud de compra.'
            );
        }

        $cotizacion->update([
            'estado' => 'ANULADA',
            'anulado_por' => $request->user()->id,
            'anulado_en' => now(),
            'motivo_anulacion' => trim((string) $request->input('motivo_anulacion')),
        ]);

        return redirect()
            ->route('cotizaciones-proveedor.show', $cotizacion->id)
            ->with(
                'success',
                'Cotización invalidada por error de registro. Sus precios no participan en las comparaciones.'
            );
    }

    public function clasificar(
        ClasificarCotizacionProveedorRequest $request,
        Cotizacion $cotizacion
    ): RedirectResponse {
        $cotizacion->load('solicitudCompra');

        if (! $cotizacion->puedeClasificar()) {
            return back()->with('error', 'Esta cotización ya tiene una decisión y no puede reclasificarse.');
        }

        $cotizacion->update([
            'estado' => $request->string('estado')->toString(),
            'evaluado_por' => $request->user()->id,
            'evaluado_en' => now(),
            'motivo_evaluacion' => trim((string) $request->input('motivo_evaluacion')),
        ]);

        return back()->with(
            'success',
            'Cotización clasificada. Conserva sus precios históricos, pero no puede originar una orden de compra.'
        );
    }

    public function reactivar(Request $request, Cotizacion $cotizacion): RedirectResponse
    {
        $cotizacion->load('solicitudCompra');

        if (! $cotizacion->estaArchivada() || $cotizacion->solicitudCompra) {
            return back()->with('error', 'Esta cotización no puede volver a quedar pendiente.');
        }

        $cotizacion->update([
            'estado' => 'REGISTRADA',
            'evaluado_por' => $request->user()->id,
            'evaluado_en' => now(),
            'motivo_evaluacion' => 'Reactivada para una nueva evaluación de compra.',
        ]);

        return back()->with('success', 'Cotización reactivada como pendiente de decisión.');
    }

    public function aprobarYGenerarOrden(
        AprobarCompraYGenerarOrdenRequest $request,
        Cotizacion $cotizacion
    ): RedirectResponse {
        $orden = $this->aprobacionCompra->ejecutar(
            $cotizacion,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('ordenes-compra.show', $orden)
            ->with('success', 'Compra aprobada y orden generada. Contabilidad ya puede consultarla para el pago.');
    }

    public function buscarProductos(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);
        $search = trim((string) ($data['q'] ?? ''));

        $query = Producto::query()
            ->with('unidadMedida')
            ->where('estado', true);

        if ($search !== '') {
            $query->where(function ($productQuery) use ($search): void {
                $productQuery
                    ->where('codigo', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%");
            })->orderByRaw(
                'CASE WHEN codigo = ? THEN 0 WHEN codigo LIKE ? THEN 1 ELSE 2 END',
                [$search, "{$search}%"]
            );
        }

        $productos = $query
            ->orderBy('codigo')
            ->limit(15)
            ->get()
            ->map(fn(Producto $producto): array => $this->productoParaSelector($producto))
            ->values();

        return response()->json(['productos' => $productos]);
    }

    public function sugerirVinculacion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo' => ['nullable', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'requisicion_id' => [
                'nullable',
                'integer',
                Rule::exists('requisiciones', 'id')->where(
                    fn($query) => $query->whereIn(
                        'estado',
                        ['ENVIADA', 'EN_REVISION', 'COTIZANDO', 'ATENDIDA']
                    )
                ),
            ],
        ]);

        $requisicion = ! empty($data['requisicion_id'])
            ? Requisicion::query()
            ->with(['detalles.producto.unidadMedida'])
            ->findOrFail($data['requisicion_id'])
            : null;
        $resultado = $this->vinculador->resolver(
            $data['codigo'] ?? null,
            $data['descripcion'] ?? null,
            $requisicion
        );

        return response()->json([
            ...$resultado,
            'lineas_requerimiento' => $this->vinculador->lineasRequerimiento($requisicion),
        ]);
    }

    public function registrarProductoRapido(
        StoreProductoRapidoCotizacionRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $revision = $this->vinculador->resolver(null, $data['descripcion'], null);
        $duplicadoSugerido = collect($revision['candidatos'] ?? [])
            ->first(fn(array $candidato): bool => (float) ($candidato['puntaje'] ?? 0) >= 99.9);
        $productoExistenteId = ! empty($revision['producto_id'])
            ? (int) $revision['producto_id']
            : (int) ($duplicadoSugerido['producto_id'] ?? 0);

        if ($productoExistenteId > 0) {
            $existente = Producto::query()
                ->with('unidadMedida')
                ->findOrFail($productoExistenteId);

            return response()->json([
                'message' => 'Ya existe un producto con la misma descripción. Selecciónalo en lugar de crear un duplicado.',
                'errors' => [
                    'descripcion' => ['Ya existe ' . $existente->codigo . ' — ' . $existente->descripcion . '.'],
                ],
                'producto_existente' => $this->productoParaSelector($existente),
            ], 422);
        }

        $similares = collect($revision['candidatos'] ?? [])
            ->filter(fn(array $candidato): bool => (float) ($candidato['puntaje'] ?? 0) >= 88)
            ->take(3)
            ->values();

        if ($similares->isNotEmpty() && ! ($data['confirmar_similitud'] ?? false)) {
            return response()->json([
                'message' => 'Encontramos productos muy parecidos. Revísalos antes de crear otro registro.',
                'errors' => [
                    'descripcion' => ['Confirma que ninguno de los productos similares corresponde al documento.'],
                ],
                'requiere_confirmacion_similitud' => true,
                'productos_similares' => $similares,
            ], 422);
        }

        try {
            $producto = DB::transaction(fn(): Producto => Producto::query()->create([
                'unidad_medida_id' => $data['unidad_medida_id'],
                'marca_principal_id' => $data['marca_principal_id'] ?? null,
                'codigo' => $data['codigo'],
                'descripcion' => $data['descripcion'],
                'estado' => true,
            ]));
        } catch (QueryException $exception) {
            if (Producto::query()->where('codigo', $data['codigo'])->exists()) {
                throw ValidationException::withMessages([
                    'codigo' => 'Este código ya pertenece a otro producto del catálogo.',
                ]);
            }

            throw $exception;
        }

        $producto->load('unidadMedida');

        return response()->json([
            'message' => 'Producto registrado y seleccionado. Se creó sin stock y sin movimiento de Kardex.',
            'producto' => $this->productoParaSelector($producto),
            'siguiente_codigo' => $this->siguienteCodigoProducto(),
        ], 201);
    }

    private function catalogos(
        ?int $providerId = null,
        array $productIds = [],
        ?int $requisitionId = null,
        array $requisitionDetailIds = []
    ): array {
        $requisicion = $requisitionId
            ? Requisicion::query()
            ->whereIn('estado', ['ENVIADA', 'EN_REVISION', 'COTIZANDO', 'ATENDIDA'])
            ->with(['detalles.producto.unidadMedida'])
            ->find($requisitionId)
            : null;

        $lineasRequisicion = $requisicion?->detalles ?? collect();
        if ($lineasRequisicion->isNotEmpty() && $requisitionDetailIds !== []) {
            $ids = collect($requisitionDetailIds)
                ->filter(fn($id): bool => is_numeric($id) && (int) $id > 0)
                ->map(fn($id): int => (int) $id)
                ->unique();
            $lineasRequisicion = $lineasRequisicion
                ->whereIn('id', $ids)
                ->values();
        }

        $productIds = collect($productIds)
            ->filter(fn($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn($id): int => (int) $id)
            ->unique()
            ->values();

        if ($productIds->isEmpty() && $requisicion) {
            $productIds = $lineasRequisicion
                ->pluck('producto_id')
                ->map(fn($id): int => (int) $id)
                ->unique()
                ->values();
        }

        $productIds = $productIds->all();

        return [
            'proveedorSeleccionado' => $providerId
                ? Proveedor::query()->find($providerId)
                : null,
            'productos' => $productIds === []
                ? collect()
                : Producto::query()
                ->with('unidadMedida')
                ->whereIn('id', $productIds)
                ->orderBy('codigo')
                ->get(),
            'unidadesProducto' => UnidadMedida::query()
                ->where('estado', true)
                ->orderBy('codigo')
                ->get(),
            'codigoProductoSugerido' => $this->siguienteCodigoProducto(),
            'requisicionSeleccionada' => $requisicion,
            'lineasRequisicion' => $requisicion
                ? $lineasRequisicion->map(fn($detalle): array => [
                    'requisicion_detalle_id' => $detalle->id,
                    'producto_id' => $detalle->producto_id,
                    'producto_codigo' => $detalle->producto?->codigo,
                    'producto_descripcion' => $detalle->producto?->descripcion,
                    'tipo_vinculacion' => 'SOLICITADO',
                    'vinculacion_origen' => 'MANUAL',
                    'vinculacion_confirmada' => true,
                    'cantidad' => $detalle->cantidad_solicitada,
                    'cantidad_requerida' => $detalle->cantidad_solicitada,
                    'precio_unitario' => '',
                    'descuento_modo' => 'SIN_DESCUENTO',
                    'descuento_tipo' => '',
                    'descuento_valor' => '',
                    'igv_modo' => 'AGREGAR',
                    'marca_ofertada' => '',
                    'observacion' => $detalle->observacion,
                ])->values()->all()
                : [],
        ];
    }

    private function vincularDetallesRequisicion(?int $requisicionId, array $detalles): array
    {
        if (! $requisicionId) {
            return collect($detalles)
                ->map(function (array $detalle, int $indice): array {
                    $coincidencia = strtoupper((string) ($detalle['coincidencia_importada'] ?? ''));
                    if (
                        in_array($coincidencia, ['SUGERIDA', 'SIN_COINCIDENCIA', 'NO_DETECTADA'], true)
                        && ! filter_var($detalle['vinculacion_confirmada'] ?? false, FILTER_VALIDATE_BOOL)
                    ) {
                        throw ValidationException::withMessages([
                            "detalles.{$indice}.producto_id" => 'Confirma manualmente el producto sugerido o seleccionado para esta línea importada.',
                        ]);
                    }

                    $detalle['requisicion_detalle_id'] = null;
                    $detalle['tipo_vinculacion'] = 'ADICIONAL';
                    $detalle['vinculacion_origen'] = $detalle['vinculacion_origen']
                        ?? (! empty($detalle['coincidencia_importada']) ? 'CONFIRMADA' : 'MANUAL');

                    return $this->limpiarEvidenciaVinculacion($detalle);
                })
                ->all();
        }

        $lineasRequisicion = Requisicion::query()
            ->findOrFail($requisicionId)
            ->detalles()
            ->get(['id', 'producto_id']);
        $porId = $lineasRequisicion->keyBy('id');
        $porProducto = $lineasRequisicion->groupBy('producto_id');

        return collect($detalles)
            ->map(function (array $detalle, int $indice) use ($porId, $porProducto): array {
                $productoId = (int) ($detalle['producto_id'] ?? 0);
                $lineaId = (int) ($detalle['requisicion_detalle_id'] ?? 0);
                $tipo = strtoupper((string) ($detalle['tipo_vinculacion'] ?? ''));
                $linea = $lineaId > 0 ? $porId->get($lineaId) : null;

                if ($lineaId > 0 && ! $linea) {
                    throw ValidationException::withMessages([
                        "detalles.{$indice}.producto_id" => 'La línea seleccionada no pertenece a este producto y requerimiento.',
                    ]);
                }

                // Compatibilidad con formularios y registros anteriores: si
                // no enviaban el tipo, se deduce sin permitir ambigüedades.
                if ($tipo === '') {
                    if ($linea && (int) $linea->producto_id !== $productoId) {
                        throw ValidationException::withMessages([
                            "detalles.{$indice}.producto_id" => 'La línea seleccionada no pertenece a este producto y requerimiento.',
                        ]);
                    }

                    $linea ??= $porProducto->get($productoId)?->first();
                    $tipo = $linea ? 'SOLICITADO' : 'ADICIONAL';
                }

                if ($tipo === 'ADICIONAL') {
                    $linea = null;
                    $detalle['requisicion_detalle_id'] = null;
                } elseif ($tipo === 'SOLICITADO') {
                    $linea ??= $porProducto->get($productoId)?->first();

                    if (! $linea || (int) $linea->producto_id !== $productoId) {
                        throw ValidationException::withMessages([
                            "detalles.{$indice}.producto_id" => 'Un producto solicitado debe coincidir con el producto de la línea del requerimiento.',
                        ]);
                    }

                    $detalle['requisicion_detalle_id'] = $linea->id;
                } elseif ($tipo === 'ALTERNATIVA') {
                    if (! $linea) {
                        throw ValidationException::withMessages([
                            "detalles.{$indice}.requisicion_detalle_id" => 'Selecciona qué producto solicitado reemplaza esta alternativa.',
                        ]);
                    }

                    if ((int) $linea->producto_id === $productoId) {
                        throw ValidationException::withMessages([
                            "detalles.{$indice}.tipo_vinculacion" => 'El mismo producto solicitado no debe registrarse como alternativa.',
                        ]);
                    }

                    $detalle['requisicion_detalle_id'] = $linea->id;
                }

                $coincidencia = strtoupper((string) ($detalle['coincidencia_importada'] ?? ''));
                if (
                    in_array($coincidencia, ['SUGERIDA', 'SIN_COINCIDENCIA', 'NO_DETECTADA'], true)
                    && ! filter_var($detalle['vinculacion_confirmada'] ?? false, FILTER_VALIDATE_BOOL)
                ) {
                    throw ValidationException::withMessages([
                        "detalles.{$indice}.producto_id" => 'Confirma manualmente el producto sugerido o seleccionado para esta línea importada.',
                    ]);
                }

                $detalle['tipo_vinculacion'] = $tipo;
                $detalle['vinculacion_origen'] = $detalle['vinculacion_origen']
                    ?? ($coincidencia !== '' ? 'CONFIRMADA' : 'MANUAL');

                return $this->limpiarEvidenciaVinculacion($detalle);
            })
            ->all();
    }

    private function limpiarEvidenciaVinculacion(array $detalle): array
    {
        $detalle['codigo_documento'] = $detalle['codigo_importado'] ?? null;
        $detalle['descripcion_documento'] = $detalle['descripcion_importada'] ?? null;

        unset(
            $detalle['codigo_importado'],
            $detalle['descripcion_importada'],
            $detalle['coincidencia_importada'],
            $detalle['vinculacion_confirmada']
        );

        return $detalle;
    }

    /**
     * Mantiene intactos los cálculos por línea. Cuando la diferencia con el
     * documento es de redondeo (máximo cinco céntimos), propone un ajuste de
     * cabecera que exige confirmación humana y conserva toda la evidencia.
     *
     * @param array<string, mixed> $cabecera
     * @param array<string, mixed> $quoteData
     * @param array<string, float> $totals
     * @return array<string, mixed>
     */
    private function conciliarImportesDocumento(
        array $cabecera,
        array $quoteData,
        array $totals,
        bool $ajusteConfirmado,
        int $usuarioId,
        ?Cotizacion $cotizacionExistente = null
    ): array {
        $importes = data_get($cabecera, 'importes_documento', []);
        $totalDocumento = $importes['total'] ?? null;

        if (! is_numeric($totalDocumento)) {
            return $totals;
        }

        $monedaDocumento = strtoupper((string) ($cabecera['moneda'] ?? ''));
        $monedaFormulario = strtoupper((string) ($quoteData['moneda'] ?? ''));

        if ($monedaDocumento !== '' && $monedaDocumento !== $monedaFormulario) {
            throw ValidationException::withMessages([
                'moneda' => sprintf(
                    'El documento fue detectado en %s. Corrige la moneda antes de conciliar sus importes.',
                    $monedaDocumento
                ),
            ]);
        }

        $baseNetaSistema = round(
            (float) ($totals['subtotal'] ?? 0)
                - (float) ($totals['descuento_global_monto'] ?? 0),
            2
        );
        $igvSistema = round((float) ($totals['impuesto'] ?? 0), 2);
        $totalSistemaPreciso = round(
            (float) ($totals['total_calculado'] ?? $totals['total'] ?? 0),
            4
        );
        $totalSistema = round($totalSistemaPreciso, 2);
        $totalDocumento = round((float) $totalDocumento, 2);
        $diferenciaTotal = abs($totalSistema - $totalDocumento);
        $diferenciaBase = 0.0;
        $diferenciaIgv = 0.0;
        $subtotalDocumento = null;
        $igvDocumento = null;

        if (is_numeric($importes['subtotal'] ?? null)) {
            $subtotalDocumento = round((float) $importes['subtotal'], 2);
            $diferenciaBase = abs($baseNetaSistema - $subtotalDocumento);
        }

        if (is_numeric($importes['igv'] ?? null)) {
            $igvDocumento = round((float) $importes['igv'], 2);
            $diferenciaIgv = abs($igvSistema - $igvDocumento);
        }

        $coincidenciaExacta = $diferenciaTotal < 0.005
            && $diferenciaBase <= 0.0100001
            && $diferenciaIgv <= 0.0100001;
        $esRedondeoAdmisible = $diferenciaTotal <= self::AJUSTE_REDONDEO_MAXIMO + 0.0000001
            && $diferenciaBase <= self::AJUSTE_REDONDEO_MAXIMO + 0.0000001
            && $diferenciaIgv <= self::AJUSTE_REDONDEO_MAXIMO + 0.0000001;

        if ($diferenciaTotal < 0.005 && ! $coincidenciaExacta) {
            throw ValidationException::withMessages([
                'reconciliacion_documento' => sprintf(
                    'El total coincide, pero la base o el IGV difieren en más de un céntimo (base %0.2f; IGV %0.2f). Revisa el tratamiento del impuesto antes de registrar.',
                    $diferenciaBase,
                    $diferenciaIgv
                ),
            ]);
        }

        if (! $coincidenciaExacta && ! $esRedondeoAdmisible) {
            throw ValidationException::withMessages([
                'reconciliacion_documento' => sprintf(
                    'La cotización no puede conciliarse por redondeo: documento %0.2f vs. sistema %0.2f (diferencia %0.2f). El máximo permitido es %0.2f. Revisa precios, cantidades, IGV o descuentos.',
                    $totalDocumento,
                    $totalSistema,
                    $diferenciaTotal,
                    self::AJUSTE_REDONDEO_MAXIMO
                ),
            ]);
        }

        $ajuste = $coincidenciaExacta
            ? 0.0
            : round($totalDocumento - $totalSistema, 2);

        if (abs($ajuste) >= 0.005 && ! $ajusteConfirmado) {
            throw ValidationException::withMessages([
                'ajuste_redondeo_confirmado' => sprintf(
                    'Confirma el ajuste por redondeo de %s %0.2f para registrar el total documental de %0.2f.',
                    $ajuste > 0 ? '+' : '-',
                    abs($ajuste),
                    $totalDocumento
                ),
            ]);
        }

        $conservaConfirmacion = $cotizacionExistente
            && abs((float) $cotizacionExistente->ajuste_redondeo - $ajuste) < 0.0001
            && $cotizacionExistente->ajuste_redondeo_confirmado_por;

        return [
            ...$totals,
            'total_calculado' => $totalSistemaPreciso,
            'ajuste_redondeo' => $ajuste,
            'total' => $totalDocumento,
            'moneda_documento' => $monedaDocumento ?: $monedaFormulario,
            'subtotal_documento' => $subtotalDocumento,
            'impuesto_documento' => $igvDocumento,
            'total_documento' => $totalDocumento,
            'ajuste_redondeo_motivo' => abs($ajuste) >= 0.005
                ? 'Diferencia de redondeo entre la suma calculada y el total declarado por el proveedor.'
                : null,
            'ajuste_redondeo_confirmado_por' => abs($ajuste) >= 0.005
                ? ($conservaConfirmacion
                    ? $cotizacionExistente->ajuste_redondeo_confirmado_por
                    : $usuarioId)
                : null,
            'ajuste_redondeo_confirmado_en' => abs($ajuste) >= 0.005
                ? ($conservaConfirmacion
                    ? $cotizacionExistente->ajuste_redondeo_confirmado_en
                    : now())
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function cabeceraDocumentoCotizacion(Cotizacion $cotizacion): array
    {
        if (is_numeric($cotizacion->total_documento)) {
            return [
                'moneda' => $cotizacion->moneda_documento ?: $cotizacion->moneda,
                'importes_documento' => [
                    'subtotal' => $cotizacion->subtotal_documento,
                    'igv' => $cotizacion->impuesto_documento,
                    'total' => $cotizacion->total_documento,
                ],
                'conciliacion' => [
                    'estado' => $cotizacion->tieneAjusteRedondeo()
                        ? 'AJUSTE_REDONDEO'
                        : 'COINCIDE',
                    'interpretacion' => $cotizacion->tieneAjusteRedondeo()
                        ? 'Total documental conciliado con ajuste por redondeo'
                        : 'Importes conciliados',
                ],
            ];
        }

        $importacion = $cotizacion->relationLoaded('importacionAsistida')
            ? $cotizacion->importacionAsistida
            : $cotizacion->importacionAsistida()->where('estado', 'CONFIRMADA')->first();

        return $importacion
            ? (array) data_get($importacion->datos_extraidos, 'cabecera', [])
            : [];
    }

    private function productosDelFormulario(
        Request $request,
        array $fallback = []
    ): array {
        $oldDetalles = $request->old('detalles');

        if (is_array($oldDetalles)) {
            return collect($oldDetalles)
                ->pluck('producto_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $fallback;
    }

    private function siguienteCodigoProducto(): string
    {
        $mayor = Producto::query()
            ->pluck('codigo')
            ->filter(fn($codigo): bool => ctype_digit(trim((string) $codigo)))
            ->map(fn($codigo): int => (int) $codigo)
            ->max();

        return (string) (($mayor ?? 0) + 1);
    }

    private function productoParaSelector(Producto $producto): array
    {
        $unidad = $producto->unidadMedida;
        $unidadVisible = $unidad
            ? ($unidad->abreviatura ?? $unidad->codigo ?? $unidad->nombre)
            : null;

        return [
            'id' => $producto->id,
            'codigo' => $producto->codigo,
            'descripcion' => $producto->descripcion,
            'unidad' => $unidadVisible,
            'label' => $producto->codigo . ' — ' . $producto->descripcion
                . ($unidadVisible ? ' · ' . $unidadVisible : ''),
        ];
    }
}
