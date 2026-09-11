<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequerimientoCompraRequest;
use App\Http\Requests\UpdateRequerimientoCompraRequest;
use App\Models\MaterialRequeridoOrden;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Requisicion;
use App\Services\Compras\AnularRequerimientoCompraService;
use App\Services\Compras\HistorialRequerimientoCompraService;
use App\Services\Compras\ProveedoresSugeridosProductoService;
use App\Services\Compras\SeguimientoAbastecimientoRequerimientoService;
use App\Services\Compras\SiguienteAccionRequerimientoService;
use App\Services\Compras\VincularAlertasRequerimientoService;
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
        private ProveedoresSugeridosProductoService $proveedoresSugeridos,
        private SeguimientoAbastecimientoRequerimientoService $seguimientoAbastecimiento,
        private SiguienteAccionRequerimientoService $siguienteAccionRequerimiento,
        private VincularAlertasRequerimientoService $vincularAlertas,
        private AnularRequerimientoCompraService $anularRequerimiento,
        private HistorialRequerimientoCompraService $historial
    ) {}

    public function index(Request $request): View
    {
        $this->autorizarConsulta($request);

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:BORRADOR,ENVIADA,EN_REVISION,COTIZANDO,ATENDIDA,ANULADA'],
            'origen' => ['nullable', 'in:REPOSICION,ORDEN_OPERACION'],
            'abastecimiento' => ['nullable', 'in:PENDIENTE,PARCIAL,COMPLETO'],
        ]);

        $query = Requisicion::query()
            ->with(['ordenOperacion.tipoOrden', 'solicitante', 'receptor'])
            ->withCount([
                'detalles',
                'cotizaciones as cotizaciones_registradas_count' => fn($cotizaciones) => $cotizaciones
                    ->where('estado', 'REGISTRADA')
                    ->whereHas('detalles'),
            ]);

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

        if (! empty($filtros['abastecimiento'])) {
            $query->where('estado_abastecimiento', $filtros['abastecimiento']);
        }

        $requerimientos = $query
            ->latest('fecha_solicitud')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $requerimientos->getCollection()->each(function (Requisicion $requerimiento) use ($request): void {
            $requerimiento->setAttribute(
                'siguiente_accion',
                $this->siguienteAccionRequerimiento->construir($request->user(), $requerimiento)
            );
        });

        $baseResumen = Requisicion::query();
        $this->aplicarVisibilidadPorRol($baseResumen, $request);

        $resumen = [
            'total' => (clone $baseResumen)->count(),
            'borradores' => (clone $baseResumen)->where('estado', 'BORRADOR')->count(),
            'recibidos' => (clone $baseResumen)->whereIn('estado', ['ENVIADA', 'EN_REVISION'])->count(),
            'cotizando' => (clone $baseResumen)->where('estado', 'COTIZANDO')->count(),
            'atendidos' => (clone $baseResumen)->where('estado', 'ATENDIDA')->count(),
            'abastecidos' => (clone $baseResumen)->where('estado_abastecimiento', 'COMPLETO')->count(),
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
        $productosCoberturaInicial = collect($detalles)->keyBy('producto_id');
        $coberturaInicial = $this->proveedoresSugeridos->coberturaSugerida(
            $productosCoberturaInicial->keys()
        );
        $alertaIdsIniciales = collect($request->old('alerta_ids', []));
        if ($alertaIdsIniciales->isEmpty() && $request->integer('alerta_id')) {
            $alertaIdsIniciales = collect([$request->integer('alerta_id')]);
        }

        return view('requerimientos_compra.create', [
            'ordenSeleccionada' => $orden,
            'detallesIniciales' => $detalles,
            'origenInicial' => $orden ? 'ORDEN_OPERACION' : 'REPOSICION',
            'productosCoberturaInicial' => $productosCoberturaInicial,
            'coberturaInicial' => $coberturaInicial,
            'alertaIdsIniciales' => $alertaIdsIniciales,
        ]);
    }

    public function store(StoreRequerimientoCompraRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $orden = $this->resolverOrden($data);
        $detalles = $data['detalles'];
        $alertaIds = $data['alerta_ids'] ?? [];
        unset($data['detalles'], $data['alerta_ids']);

        if ($data['origen'] === 'REPOSICION') {
            $data['orden_operacion_id'] = null;
        }

        $requerimiento = $this->codigos->usarSiguiente(
            'requisiciones',
            'REQ',
            $data['fecha_solicitud'],
            function (string $codigo) use ($data, $detalles, $alertaIds, $request, $orden): Requisicion {
                return DB::transaction(function () use ($codigo, $data, $detalles, $alertaIds, $request, $orden): Requisicion {
                    $requerimiento = Requisicion::query()->create([
                        ...$data,
                        'codigo' => $codigo,
                        'estado' => 'BORRADOR',
                        'solicitado_por' => $request->user()->id,
                    ]);

                    $this->guardarDetalles($requerimiento, $detalles, $orden?->id);
                    $this->vincularAlertas->vincular($requerimiento, $alertaIds);
                    $this->historial->registrarInicial(
                        $requerimiento,
                        $request->user(),
                        'Borrador creado por Almacén.'
                    );

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
            'detalles.cotizacionDetalles.cotizacion.proveedor',
            'cotizaciones.proveedor',
            'cotizaciones.detalles',
            'alertasStock.producto',
            'historial.usuario',
        ])->loadCount('cotizaciones');

        $proveedoresPorProducto = $this->proveedoresSugeridos
            ->porProducto($requerimientoCompra->detalles->pluck('producto_id'));
        $coberturaSugerida = $this->proveedoresSugeridos->coberturaSugerida(
            $requerimientoCompra->detalles->pluck('producto_id'),
            $proveedoresPorProducto
        );
        $seguimientoAbastecimiento = $this->seguimientoAbastecimiento
            ->construir($requerimientoCompra);
        $siguienteAccion = $this->siguienteAccionRequerimiento->construir(
            $request->user(),
            $requerimientoCompra,
            (int) ($seguimientoAbastecimiento['cotizadas'] ?? 0) > 0
        );

        $gruposSugeridos = $coberturaSugerida['grupos']
            ->map(function (array $grupo) use ($requerimientoCompra): array {
                $detalles = $requerimientoCompra->detalles
                    ->whereIn('producto_id', $grupo['producto_ids']);

                return [
                    ...$grupo,
                    'productos' => $detalles
                        ->map(fn ($detalle): string => $detalle->producto->codigo.' — '.$detalle->producto->descripcion)
                        ->values(),
                    'detalle_ids' => $detalles->pluck('id')->values(),
                ];
            });

        $productosSinProveedor = $requerimientoCompra->detalles
            ->whereIn('producto_id', $coberturaSugerida['sin_proveedor'])
            ->map(fn ($detalle): string => $detalle->producto->codigo.' — '.$detalle->producto->descripcion)
            ->values();

        $tieneOrdenCompraActiva = $this->anularRequerimiento
            ->tieneOrdenCompraActiva($requerimientoCompra);

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

                $detalleIds = $requerimientoCompra->detalles
                    ->whereIn('producto_id', $productoIds)
                    ->pluck('id')
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
                    'detalle_ids' => $detalleIds,
                    'ultima_cotizacion' => $filas->max('ultima_cotizacion'),
                ];
            })
            ->sortByDesc('ultima_cotizacion')
            ->values();

        return view('requerimientos_compra.show', [
            'requerimiento' => $requerimientoCompra,
            'proveedoresPorProducto' => $proveedoresPorProducto,
            'contactos' => $contactos,
            'gruposSugeridos' => $gruposSugeridos,
            'productosSinProveedor' => $productosSinProveedor,
            'coberturaTotal' => $coberturaSugerida['cobertura_total'],
            'seguimientoAbastecimiento' => $seguimientoAbastecimiento,
            'siguienteAccion' => $siguienteAccion,
            'puedeEditar' => $this->puedeEditar($request, $requerimientoCompra),
            'puedeAnular' => $this->puedeAnular($request, $requerimientoCompra)
                && ! $tieneOrdenCompraActiva,
            'tieneOrdenCompraActiva' => $tieneOrdenCompraActiva,
            'puedeGestionar' => $request->user()->puede('requerimientos.compra.gestionar') || $request->user()->esAdministrador(),
            'puedeDescargarSolicitud' => ! $requerimientoCompra->estaAnulada() && (
                $request->user()->puede('requerimientos.compra.crear')
                || $request->user()->puede('requerimientos.compra.gestionar')
                || $request->user()->esAdministrador()
            ),
        ]);
    }

    public function edit(Request $request, Requisicion $requerimientoCompra): View
    {
        abort_unless($this->puedeEditar($request, $requerimientoCompra), 403);

        $requerimientoCompra->load([
            'ordenOperacion.tipoOrden',
            'ordenOperacion.cliente',
            'detalles.producto.unidadMedida',
            'alertasStock',
        ]);

        return view('requerimientos_compra.edit', [
            'requerimiento' => $requerimientoCompra,
            'ordenSeleccionada' => $requerimientoCompra->ordenOperacion,
            'origenInicial' => $requerimientoCompra->origen,
            'alertaIdsIniciales' => $requerimientoCompra->alertasStock->pluck('id'),
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
        $alertaIds = $data['alerta_ids'] ?? [];
        unset($data['detalles'], $data['alerta_ids']);

        if ($data['origen'] === 'REPOSICION') {
            $data['orden_operacion_id'] = null;
        }

        DB::transaction(function () use ($requerimientoCompra, $data, $detalles, $alertaIds, $orden): void {
            $requerimientoCompra->update($data);
            $requerimientoCompra->detalles()->delete();
            $this->guardarDetalles($requerimientoCompra, $detalles, $orden?->id);
            $this->vincularAlertas->vincular($requerimientoCompra, $alertaIds);
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

        DB::transaction(function () use ($requerimientoCompra, $request): void {
            $this->historial->cambiarEstado(
                $requerimientoCompra,
                ['BORRADOR'],
                'ENVIADA',
                $request->user(),
                'Requerimiento enviado por Almacén a Logística/Compras.',
                [
                    'enviado_por' => $request->user()->id,
                    'enviado_en' => now(),
                ]
            );
            $this->vincularAlertas->marcarEnGestion($requerimientoCompra, $request->user());
        });

        return back()->with('success', "{$requerimientoCompra->codigo} fue enviado a Logística/Compras.");
    }

    public function recibir(Request $request, Requisicion $requerimientoCompra): RedirectResponse
    {
        $this->autorizarGestion($request);
        $data = $this->validarSeguimiento($request);

        $this->historial->cambiarEstado(
            $requerimientoCompra,
            ['ENVIADA'],
            'EN_REVISION',
            $request->user(),
            $this->notaSeguimiento($data, 'Requerimiento tomado para revisión por Logística.'),
            [
                'recibido_por' => $request->user()->id,
                'recibido_en' => now(),
            ]
        );

        return back()->with('success', 'Requerimiento tomado para revisión.');
    }

    public function cotizando(Request $request, Requisicion $requerimientoCompra): RedirectResponse
    {
        $this->autorizarGestion($request);
        $data = $this->validarSeguimiento($request);

        $this->historial->cambiarEstado(
            $requerimientoCompra,
            ['EN_REVISION'],
            'COTIZANDO',
            $request->user(),
            $this->notaSeguimiento($data, 'Logística inició la etapa de cotización.')
        );

        return back()->with('success', 'El requerimiento quedó marcado como en cotización.');
    }

    public function atender(Request $request, Requisicion $requerimientoCompra): RedirectResponse
    {
        $this->autorizarGestion($request);
        $data = $this->validarSeguimiento($request);

        $this->historial->cambiarEstado(
            $requerimientoCompra,
            ['COTIZANDO'],
            'ATENDIDA',
            $request->user(),
            $this->notaSeguimiento($data, 'Logística marcó el requerimiento como atendido.'),
            [
                'atendido_por' => $request->user()->id,
                'atendido_en' => now(),
            ]
        );

        return back()->with('success', 'Requerimiento marcado como atendido.');
    }

    public function anular(Request $request, Requisicion $requerimientoCompra): RedirectResponse
    {
        abort_unless($this->puedeAnular($request, $requerimientoCompra), 403);

        $data = $request->validate([
            'motivo_anulacion' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $requerimiento = $this->anularRequerimiento->ejecutar(
            $requerimientoCompra,
            $request->user(),
            trim($data['motivo_anulacion'])
        );

        return redirect()
            ->route('requerimientos-compra.show', $requerimiento)
            ->with('success', "El requerimiento {$requerimiento->codigo} fue anulado y sus alertas quedaron liberadas.");
    }

    /** @return array{observacion_seguimiento?: string|null} */
    private function validarSeguimiento(Request $request): array
    {
        return $request->validate([
            'observacion_seguimiento' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /** @param array{observacion_seguimiento?: string|null} $data */
    private function notaSeguimiento(array $data, string $predeterminada): string
    {
        $nota = trim((string) ($data['observacion_seguimiento'] ?? ''));

        return $nota !== '' ? $nota : $predeterminada;
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

    private function puedeAnular(Request $request, Requisicion $requerimiento): bool
    {
        if ($requerimiento->estaAnulada()) {
            return false;
        }

        $usuario = $request->user();
        if ($usuario->esAdministrador()) {
            return true;
        }

        if ($usuario->tieneRol('ALMACEN')) {
            return $requerimiento->esBorrador()
                || ($requerimiento->estaEnviada() && ! $requerimiento->recibido_en);
        }

        return $usuario->tieneRol('COMERCIAL_LOGISTICA')
            && in_array(
                $requerimiento->estado,
                ['ENVIADA', 'EN_REVISION', 'COTIZANDO', 'ATENDIDA'],
                true
            );
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
