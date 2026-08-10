<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequerimientoCompraRequest;
use App\Http\Requests\UpdateRequerimientoCompraRequest;
use App\Models\MaterialRequeridoOrden;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Requisicion;
use App\Services\Compras\ProveedoresSugeridosProductoService;
use App\Services\Documentos\GenerarCodigoDocumentoService;
use App\Services\Inventario\DisponibilidadMaterialService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RequerimientoCompraController extends Controller
{
    public function __construct(
        private GenerarCodigoDocumentoService $codigos,
        private DisponibilidadMaterialService $disponibilidad,
        private ProveedoresSugeridosProductoService $proveedoresSugeridos
    ) {}

    public function index(Request $request): View
    {
        $this->autorizarConsulta($request);

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:BORRADOR,ENVIADA,EN_REVISION,COTIZANDO,ATENDIDA,ANULADA'],
            'origen' => ['nullable', 'in:REPOSICION,ORDEN_OPERACION'],
        ]);

        $query = Requisicion::query()
            ->with(['ordenOperacion.tipoOrden', 'solicitante'])
            ->withCount('detalles');

        $this->aplicarVisibilidadPorRol($query, $request);

        if (! empty($filtros['q'])) {
            $termino = trim($filtros['q']);
            $query->where(function (Builder $busqueda) use ($termino): void {
                $busqueda
                    ->where('codigo', 'like', "%{$termino}%")
                    ->orWhere('descripcion', 'like', "%{$termino}%")
                    ->orWhereHas('ordenOperacion', fn(Builder $orden) => $orden
                        ->where('codigo_orden', 'like', "%{$termino}%"));
            });
        }

        foreach (['estado', 'origen'] as $campo) {
            if (! empty($filtros[$campo])) {
                $query->where($campo, $filtros[$campo]);
            }
        }

        $requerimientos = $query
            ->latest('fecha_solicitud')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $baseResumen = Requisicion::query();
        $this->aplicarVisibilidadPorRol($baseResumen, $request);

        $resumen = [
            'total' => (clone $baseResumen)->count(),
            'borradores' => (clone $baseResumen)->where('estado', 'BORRADOR')->count(),
            'recibidos' => (clone $baseResumen)->whereIn('estado', ['ENVIADA', 'EN_REVISION'])->count(),
            'cotizando' => (clone $baseResumen)->where('estado', 'COTIZANDO')->count(),
            'atendidos' => (clone $baseResumen)->where('estado', 'ATENDIDA')->count(),
        ];

        return view('requerimientos_compra.index', [
            'requerimientos' => $requerimientos,
            'resumen' => $resumen,
            'puedeCrear' => $request->user()->tieneRol('ALMACEN') || $request->user()->esAdministrador(),
            'esLogistica' => $request->user()->tieneRol('COMERCIAL_LOGISTICA') || $request->user()->esAdministrador(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->autorizarCreacion($request);

        $ordenId = $request->integer('orden_operacion_id') ?: null;
        $orden = $ordenId
            ? OrdenOperacion::query()->with(['tipoOrden', 'cliente'])->find($ordenId)
            : null;

        if ($orden && $orden->estado !== 'EN_PROCESO') {
            $orden = null;
        }

        $productoId = $request->integer('producto_id') ?: null;
        $fallbackDetalles = $productoId
            ? [[
                'producto_id' => $productoId,
                'cantidad_solicitada' => $request->input('cantidad') ?: null,
                'observacion' => null,
            ]]
            : $this->faltantesSugeridosOrden($orden);

        $detalles = $this->detallesFormulario(
            $request,
            $fallbackDetalles,
            $orden?->id
        );

        return view('requerimientos_compra.create', [
            'ordenSeleccionada' => $orden,
            'detallesIniciales' => $detalles,
            'origenInicial' => $orden ? 'ORDEN_OPERACION' : 'REPOSICION',
        ]);
    }

    public function store(StoreRequerimientoCompraRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $orden = $this->resolverOrden($data);
        $detalles = $data['detalles'];
        unset($data['detalles']);

        if ($data['origen'] === 'REPOSICION') {
            $data['orden_operacion_id'] = null;
        }

        $requerimiento = $this->codigos->usarSiguiente(
            'requisiciones',
            'REQ',
            $data['fecha_solicitud'],
            function (string $codigo) use ($data, $detalles, $request, $orden): Requisicion {
                return DB::transaction(function () use ($codigo, $data, $detalles, $request, $orden): Requisicion {
                    $requerimiento = Requisicion::query()->create([
                        ...$data,
                        'codigo' => $codigo,
                        'estado' => 'BORRADOR',
                        'solicitado_por' => $request->user()->id,
                    ]);

                    $this->guardarDetalles($requerimiento, $detalles, $orden?->id);

                    return $requerimiento;
                });
            }
        );

        return redirect()
            ->route('requerimientos-compra.show', $requerimiento)
            ->with('success', "Requerimiento {$requerimiento->codigo} guardado como borrador.");
    }

    public function show(Request $request, Requisicion $requerimientoCompra): View
    {
        $this->autorizarVer($request, $requerimientoCompra);

        $requerimientoCompra->load([
            'ordenOperacion.tipoOrden',
            'ordenOperacion.cliente',
            'solicitante',
            'enviador',
            'receptor',
            'atendidoPor',
            'detalles.producto.unidadMedida',
            'cotizaciones.proveedor',
        ])->loadCount('cotizaciones');

        $proveedoresPorProducto = $this->proveedoresSugeridos
            ->porProducto($requerimientoCompra->detalles->pluck('producto_id'));

        $contactos = $proveedoresPorProducto
            ->flatten(1)
            ->groupBy('proveedor_id')
            ->map(function ($filas) use ($requerimientoCompra): array {
                $primero = $filas->first();
                $productoIds = $filas->pluck('producto_id')->unique();
                $productos = $requerimientoCompra->detalles
                    ->whereIn('producto_id', $productoIds)
                    ->pluck('producto')
                    ->filter()
                    ->map(fn(Producto $producto): string => $producto->codigo)
                    ->values();

                return [
                    'proveedor_id' => (int) $primero->proveedor_id,
                    'nombre' => $primero->nombre_comercial ?: $primero->razon_social,
                    'razon_social' => $primero->razon_social,
                    'ruc' => $primero->ruc,
                    'telefono' => $primero->telefono,
                    'correo' => $primero->correo,
                    'contacto' => $primero->contacto,
                    'productos' => $productos,
                    'ultima_cotizacion' => $filas->max('ultima_cotizacion'),
                ];
            })
            ->sortByDesc('ultima_cotizacion')
            ->values();

        return view('requerimientos_compra.show', [
            'requerimiento' => $requerimientoCompra,
            'proveedoresPorProducto' => $proveedoresPorProducto,
            'contactos' => $contactos,
            'puedeEditar' => $this->puedeEditar($request, $requerimientoCompra),
            'puedeGestionar' => $request->user()->puede('requerimientos.compra.gestionar') || $request->user()->esAdministrador(),
        ]);
    }

    public function edit(Request $request, Requisicion $requerimientoCompra): View
    {
        abort_unless($this->puedeEditar($request, $requerimientoCompra), 403);

        $requerimientoCompra->load(['ordenOperacion.tipoOrden', 'ordenOperacion.cliente', 'detalles.producto.unidadMedida']);

        return view('requerimientos_compra.edit', [
            'requerimiento' => $requerimientoCompra,
            'ordenSeleccionada' => $requerimientoCompra->ordenOperacion,
            'origenInicial' => $requerimientoCompra->origen,
            'detallesIniciales' => $this->detallesFormulario(
                $request,
                $requerimientoCompra->detalles->map(fn($detalle): array => [
                    'producto_id' => $detalle->producto_id,
                    'cantidad_solicitada' => $detalle->cantidad_solicitada,
                    'observacion' => $detalle->observacion,
                ])->all(),
                $requerimientoCompra->orden_operacion_id
            ),
        ]);
    }

    public function update(
        UpdateRequerimientoCompraRequest $request,
        Requisicion $requerimientoCompra
    ): RedirectResponse {
        abort_unless($this->puedeEditar($request, $requerimientoCompra), 403);

        $data = $request->validated();
        $orden = $this->resolverOrden($data);
        $detalles = $data['detalles'];
        unset($data['detalles']);

        if ($data['origen'] === 'REPOSICION') {
            $data['orden_operacion_id'] = null;
        }

        DB::transaction(function () use ($requerimientoCompra, $data, $detalles, $orden): void {
            $requerimientoCompra->update($data);
            $requerimientoCompra->detalles()->delete();
            $this->guardarDetalles($requerimientoCompra, $detalles, $orden?->id);
        });

        return redirect()
            ->route('requerimientos-compra.show', $requerimientoCompra)
            ->with('success', 'El requerimiento fue actualizado.');
    }

    public function enviar(Request $request, Requisicion $requerimientoCompra): RedirectResponse
    {
        $this->autorizarCreacion($request);
        abort_unless($requerimientoCompra->esBorrador(), 422, 'Solo los borradores pueden enviarse.');
        abort_if($requerimientoCompra->detalles()->count() === 0, 422, 'El requerimiento no tiene productos.');

        if ($requerimientoCompra->origen === 'ORDEN_OPERACION') {
            $orden = $requerimientoCompra->ordenOperacion()->first();
            if (! $orden || $orden->estado !== 'EN_PROCESO') {
                return back()->with('error', 'La orden relacionada ya no está en ejecución. Revisa el requerimiento antes de enviarlo.');
            }
        }

        $requerimientoCompra->update([
            'estado' => 'ENVIADA',
            'enviado_por' => $request->user()->id,
            'enviado_en' => now(),
        ]);

        return back()->with('success', "{$requerimientoCompra->codigo} fue enviado a Logística/Compras.");
    }

    public function recibir(Request $request, Requisicion $requerimientoCompra): RedirectResponse
    {
        $this->autorizarGestion($request);
        abort_unless($requerimientoCompra->estaEnviada(), 422, 'El requerimiento ya fue tomado o no está enviado.');

        $requerimientoCompra->update([
            'estado' => 'EN_REVISION',
            'recibido_por' => $request->user()->id,
            'recibido_en' => now(),
        ]);

        return back()->with('success', 'Requerimiento tomado para revisión.');
    }

    public function cotizando(Request $request, Requisicion $requerimientoCompra): RedirectResponse
    {
        $this->autorizarGestion($request);
        abort_unless($requerimientoCompra->estaEnRevision(), 422, 'Primero toma el requerimiento para revisión.');

        $requerimientoCompra->update(['estado' => 'COTIZANDO']);

        return back()->with('success', 'El requerimiento quedó marcado como en cotización.');
    }

    public function atender(Request $request, Requisicion $requerimientoCompra): RedirectResponse
    {
        $this->autorizarGestion($request);
        abort_unless(
            in_array($requerimientoCompra->estado, ['EN_REVISION', 'COTIZANDO'], true),
            422,
            'El requerimiento no puede cerrarse desde su estado actual.'
        );

        $requerimientoCompra->update([
            'estado' => 'ATENDIDA',
            'atendido_por' => $request->user()->id,
            'atendido_en' => now(),
        ]);

        return back()->with('success', 'Requerimiento marcado como atendido.');
    }

    private function resolverOrden(array $data): ?OrdenOperacion
    {
        if (($data['origen'] ?? '') !== 'ORDEN_OPERACION') {
            return null;
        }

        $orden = OrdenOperacion::query()
            ->with('tipoOrden')
            ->findOrFail((int) $data['orden_operacion_id']);

        if ($orden->estado !== 'EN_PROCESO') {
            throw ValidationException::withMessages([
                'orden_operacion_id' => 'Solo puedes vincular una OM/OS/OP que esté en ejecución.',
            ]);
        }

        return $orden;
    }

    private function guardarDetalles(Requisicion $requerimiento, array $detalles, ?int $ordenId): void
    {
        $resumenes = $this->disponibilidad->resumenesProductos(
            collect($detalles)->pluck('producto_id'),
            $ordenId
        );

        foreach ($detalles as $detalle) {
            $productoId = (int) $detalle['producto_id'];
            $resumen = $resumenes->get($productoId, []);

            $requerimiento->detalles()->create([
                'producto_id' => $productoId,
                'cantidad_solicitada' => $detalle['cantidad_solicitada'],
                'cantidad_sugerida' => (float) ($resumen['necesidad_abastecimiento'] ?? 0),
                'cantidad_atendida' => 0,
                'stock_fisico_snapshot' => (float) ($resumen['stock_fisico'] ?? 0),
                'reservado_snapshot' => (float) ($resumen['reservado'] ?? 0),
                'disponible_snapshot' => (float) ($resumen['disponible'] ?? 0),
                'stock_minimo_snapshot' => (float) ($resumen['stock_minimo'] ?? 0),
                'observacion' => $detalle['observacion'] ?? null,
            ]);
        }
    }


    /** @return array<int, array{producto_id:int,cantidad_solicitada:float,observacion:?string}> */
    private function faltantesSugeridosOrden(?OrdenOperacion $orden): array
    {
        if (! $orden || $orden->estado !== 'EN_PROCESO') {
            return [];
        }

        $productoIds = MaterialRequeridoOrden::query()
            ->where('orden_operacion_id', $orden->id)
            ->pluck('producto_id')
            ->map(fn($id): int => (int) $id)
            ->unique()
            ->values();

        if ($productoIds->isEmpty()) {
            return [];
        }

        $resumenes = $this->disponibilidad->resumenesProductos($productoIds, $orden->id);

        return $productoIds
            ->map(function (int $productoId) use ($resumenes): ?array {
                $sugerida = round((float) ($resumenes->get($productoId)['necesidad_abastecimiento'] ?? 0), 3);
                if ($sugerida <= 0) {
                    return null;
                }

                return [
                    'producto_id' => $productoId,
                    'cantidad_solicitada' => $sugerida,
                    'observacion' => 'Faltante sugerido por la orden activa.',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function detallesFormulario(Request $request, array $fallback, ?int $ordenId): array
    {
        $detalles = $request->old('detalles', $fallback);
        if (! is_array($detalles) || $detalles === []) {
            return [];
        }

        $ids = collect($detalles)
            ->pluck('producto_id')
            ->filter()
            ->map(fn($id): int => (int) $id)
            ->unique()
            ->values();

        $productos = Producto::query()
            ->with('unidadMedida')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
        $resumenes = $this->disponibilidad->resumenesProductos($ids, $ordenId);

        return collect($detalles)
            ->map(function (array $detalle) use ($productos, $resumenes): ?array {
                $productoId = (int) ($detalle['producto_id'] ?? 0);
                $producto = $productos->get($productoId);
                if (! $producto) {
                    return null;
                }

                $unidad = $producto->unidadMedida?->abreviatura
                    ?? $producto->unidadMedida?->codigo
                    ?? $producto->unidadMedida?->nombre;
                $resumen = $resumenes->get($productoId, []);
                $cantidad = $detalle['cantidad_solicitada'] ?? null;
                if (($cantidad === null || $cantidad === '') && (float) ($resumen['necesidad_abastecimiento'] ?? 0) > 0) {
                    $cantidad = $resumen['necesidad_abastecimiento'];
                }

                return [
                    'producto_id' => $productoId,
                    'codigo' => $producto->codigo,
                    'descripcion' => $producto->descripcion,
                    'unidad' => $unidad,
                    'cantidad_solicitada' => $cantidad ?: 1,
                    'observacion' => $detalle['observacion'] ?? null,
                    'stock_fisico' => (float) ($resumen['stock_fisico'] ?? 0),
                    'reservado' => (float) ($resumen['reservado'] ?? 0),
                    'disponible' => (float) ($resumen['disponible'] ?? 0),
                    'stock_minimo' => (float) ($resumen['stock_minimo'] ?? 0),
                    'cantidad_sugerida' => (float) ($resumen['necesidad_abastecimiento'] ?? 0),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function aplicarVisibilidadPorRol(Builder $query, Request $request): void
    {
        $usuario = $request->user();

        if ($usuario->esAdministrador() || $usuario->tieneRol('ALMACEN')) {
            return;
        }

        // Los borradores son documentos internos de Almacén. Logística recién
        // puede verlos cuando Almacén los envía, independientemente de cualquier
        // permiso adicional que pudiera asignarse al rol en el futuro.
        $query->where('estado', '!=', 'BORRADOR');
    }

    private function puedeEditar(Request $request, Requisicion $requerimiento): bool
    {
        return $requerimiento->esBorrador()
            && ($request->user()->tieneRol('ALMACEN') || $request->user()->esAdministrador());
    }

    private function autorizarConsulta(Request $request): void
    {
        abort_unless(
            $request->user()->tieneRol('ALMACEN', 'COMERCIAL_LOGISTICA')
                || $request->user()->esAdministrador(),
            403
        );
    }

    private function autorizarCreacion(Request $request): void
    {
        abort_unless(
            $request->user()->tieneRol('ALMACEN') || $request->user()->esAdministrador(),
            403,
            'Solo Almacén puede crear y enviar requerimientos de compra.'
        );
    }

    private function autorizarGestion(Request $request): void
    {
        abort_unless(
            $request->user()->tieneRol('COMERCIAL_LOGISTICA') || $request->user()->esAdministrador(),
            403,
            'Solo Logística/Compras puede gestionar requerimientos enviados.'
        );
    }

    private function autorizarVer(Request $request, Requisicion $requerimiento): void
    {
        $this->autorizarConsulta($request);

        if (
            $requerimiento->esBorrador()
            && ! $request->user()->tieneRol('ALMACEN')
            && ! $request->user()->esAdministrador()
        ) {
            abort(403, 'Este borrador todavía pertenece a Almacén.');
        }
    }
}
