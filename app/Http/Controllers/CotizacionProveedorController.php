<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularCotizacionProveedorRequest;
use App\Http\Requests\StoreCotizacionProveedorRequest;
use App\Http\Requests\StoreProductoRapidoCotizacionRequest;
use App\Http\Requests\UpdateCotizacionProveedorRequest;
use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\UnidadMedida;
use App\Services\Compras\CalcularCotizacionProveedorService;
use App\Services\Documentos\GenerarCodigoDocumentoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CotizacionProveedorController extends Controller
{
    public function __construct(
        private GenerarCodigoDocumentoService $codigos,
        private CalcularCotizacionProveedorService $calculador
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'moneda' => ['nullable', 'in:PEN,USD'],
            'estado' => ['nullable', 'in:REGISTRADA,SELECCIONADA,ANULADA'],
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
            'vigentes' => Cotizacion::query()->where('estado', '!=', 'ANULADA')->count(),
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
        return view(
            'cotizaciones_proveedor.create',
            $this->catalogos(
                (int) $request->old(
                    'proveedor_id',
                    $request->integer('proveedor_id') ?: null
                ) ?: null,
                $this->productosDelFormulario($request),
                (int) $request->old('requisicion_id') ?: null
            )
        );
    }

    public function store(
        StoreCotizacionProveedorRequest $request
    ): RedirectResponse {
        $data = $request->validated();
        $details = $data['detalles'];
        unset($data['detalles']);

        $quote = $this->codigos->usarSiguiente(
            'cotizaciones',
            'CP',
            $data['fecha_cotizacion'],
            function (string $code) use (
                $data,
                $details,
                $request
            ): Cotizacion {
                return DB::transaction(function () use (
                    $code,
                    $data,
                    $details,
                    $request
                ): Cotizacion {
                    [$lines, $totals] = $this->calculador->calcular(
                        $details,
                        $data['descuento_global_modo'],
                        $data['descuento_global_tipo'],
                        (float) ($data['descuento_global_valor'] ?? 0)
                    );

                    $quote = Cotizacion::query()->create([
                        ...$data,
                        ...$totals,
                        'codigo' => $code,
                        'estado' => 'REGISTRADA',
                        'registrado_por' => $request->user()->id,
                    ]);

                    $quote->detalles()->createMany($lines);

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
            'solicitudCompra',
            'detalles.producto.unidadMedida',
        ]);

        return view('cotizaciones_proveedor.show', compact('cotizacion'));
    }

    public function edit(
        Request $request,
        Cotizacion $cotizacion
    ): View|RedirectResponse {
        $cotizacion->load(['detalles', 'solicitudCompra']);

        if (! $cotizacion->puedeEditar()) {
            return redirect()
                ->route('cotizaciones-proveedor.show', $cotizacion->id)
                ->with('error', 'La cotización anulada o utilizada no puede editarse.');
        }

        return view('cotizaciones_proveedor.edit', [
            'cotizacion' => $cotizacion,
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
        $details = $data['detalles'];
        unset($data['detalles']);

        DB::transaction(function () use (
            $cotizacion,
            $data,
            $details
        ): void {
            [$lines, $totals] = $this->calculador->calcular(
                $details,
                $data['descuento_global_modo'],
                $data['descuento_global_tipo'],
                (float) ($data['descuento_global_valor'] ?? 0)
            );

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

    public function registrarProductoRapido(
        StoreProductoRapidoCotizacionRequest $request
    ): JsonResponse {
        $data = $request->validated();

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
        ?int $requisitionId = null
    ): array {
        $productIds = collect($productIds)
            ->filter(fn($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

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
            'requisicionSeleccionada' => $requisitionId
                ? Requisicion::query()->find($requisitionId)
                : null,
        ];
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
