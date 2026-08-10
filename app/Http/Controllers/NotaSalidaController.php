<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularNotaSalidaRequest;
use App\Http\Requests\StoreNotaSalidaRequest;
use App\Models\Inventario;
use App\Models\NotaSalida;
use App\Models\OrdenOperacion;
use App\Models\Proforma;
use App\Services\Inventario\AnularNotaSalidaService;
use App\Services\Inventario\DisponibilidadMaterialService;
use App\Services\Inventario\EvaluarAlertasStockService;
use App\Services\Inventario\RegistrarNotaSalidaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NotaSalidaController extends Controller
{
    public function __construct(private DisponibilidadMaterialService $disponibilidad) {}

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:CONFIRMADA,ANULADA,BORRADOR'],
            'motivo' => ['nullable', 'in:ORDEN_OPERACION,PROFORMA,USO_INTERNO,OTRO'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $query = NotaSalida::query()
            ->with([
                'ordenOperacion.tipoOrden',
                'ordenOperacion.cliente',
                'ordenOperacion.vehiculo',
                'proforma.cliente',
                'registrador',
            ])
            ->withCount('detalles')
            ->withSum('detalles as cantidad_total', 'cantidad')
            ->withSum('detalles as importe_total', 'subtotal');

        if (! empty($filtros['q'])) {
            $busqueda = trim($filtros['q']);
            $query->where(function ($subquery) use ($busqueda): void {
                $subquery
                    ->where('codigo', 'like', "%{$busqueda}%")
                    ->orWhere('entregado_a', 'like', "%{$busqueda}%")
                    ->orWhereHas('ordenOperacion', function ($orden) use ($busqueda): void {
                        $orden
                            ->where('codigo_orden', 'like', "%{$busqueda}%")
                            ->orWhere('descripcion', 'like', "%{$busqueda}%");
                    })
                    ->orWhereHas('ordenOperacion.cliente', function ($cliente) use ($busqueda): void {
                        $cliente
                            ->where('razon_social', 'like', "%{$busqueda}%")
                            ->orWhere('ruc', 'like', "%{$busqueda}%");
                    })
                    ->orWhereHas('ordenOperacion.vehiculo', function ($vehiculo) use ($busqueda): void {
                        $vehiculo
                            ->where('placa', 'like', "%{$busqueda}%")
                            ->orWhere('codigo_interno', 'like', "%{$busqueda}%");
                    })
                    ->orWhereHas(
                        'proforma',
                        fn($proforma) =>
                        $proforma->where('codigo', 'like', "%{$busqueda}%")
                    )
                    ->orWhereHas('proforma.cliente', function ($cliente) use ($busqueda): void {
                        $cliente
                            ->where('razon_social', 'like', "%{$busqueda}%")
                            ->orWhere('ruc', 'like', "%{$busqueda}%");
                    });
            });
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }
        if (! empty($filtros['motivo'])) {
            $query->where('motivo_salida', $filtros['motivo']);
        }
        if (! empty($filtros['desde'])) {
            $query->whereDate('fecha_salida', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $query->whereDate('fecha_salida', '<=', $filtros['hasta']);
        }

        $notas = $query
            ->orderByDesc('fecha_salida')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $resumen = NotaSalida::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN estado = 'CONFIRMADA' THEN 1 ELSE 0 END) as confirmadas")
            ->selectRaw("SUM(CASE WHEN estado = 'ANULADA' THEN 1 ELSE 0 END) as anuladas")
            ->selectRaw(
                "SUM(CASE WHEN fecha_salida = ? AND estado = 'CONFIRMADA' THEN 1 ELSE 0 END) as hoy",
                [now()->toDateString()]
            )
            ->first();

        $valorEntregado = (float) NotaSalida::query()
            ->join('nota_salida_detalles as d', 'd.nota_salida_id', '=', 'notas_salida.id')
            ->where('notas_salida.estado', 'CONFIRMADA')
            ->sum('d.subtotal');

        return view('notas_salida.index', compact('notas', 'resumen', 'valorEntregado'));
    }

    public function create(Request $request): View
    {
        $motivo = (string) old(
            'motivo_salida',
            $request->query('motivo_salida', 'ORDEN_OPERACION')
        );
        if (! in_array($motivo, StoreNotaSalidaRequest::MOTIVOS, true)) {
            $motivo = 'ORDEN_OPERACION';
        }

        $ordenId = (int) old('orden_operacion_id', $request->integer('orden_operacion_id'));
        $proformaId = (int) old('proforma_id', $request->integer('proforma_id'));
        $orden = null;
        $proforma = null;
        $origenNoDisponible = false;

        if ($motivo === 'ORDEN_OPERACION' && $ordenId > 0) {
            $orden = OrdenOperacion::query()
                ->with([
                    'tipoOrden',
                    'cliente',
                    'vehiculo',
                    'materialesRequeridos.producto.unidadMedida',
                ])
                ->where('estado', 'EN_PROCESO')
                ->find($ordenId);
            $origenNoDisponible = ! $orden;
        }

        if ($motivo === 'PROFORMA' && $proformaId > 0) {
            $proforma = Proforma::query()
                ->with(['cliente', 'detalles.producto.unidadMedida', 'detalles.reposiciones'])
                ->where('estado', '!=', 'ANULADA')
                ->find($proformaId);
            $origenNoDisponible = ! $proforma;
        }

        $origenListo = match ($motivo) {
            'ORDEN_OPERACION' => (bool) $orden,
            'PROFORMA' => (bool) $proforma,
            default => true,
        };

        $materialesOrden = $orden
            ? $this->resumenMaterialesOrden($orden)
            : collect();

        $filas = $origenListo
            ? $this->filasDisponibles($motivo, $orden, $proforma, $materialesOrden)
            : collect();

        $pendientesOrden = $materialesOrden
            ->filter(fn(array $material): bool => $material['pendiente'] > 0.0001)
            ->values();
        $materialesSinExistencia = $materialesOrden
            ->filter(fn(array $material): bool => $material['stock_fisico'] <= 0.0001)
            ->values();
        $pendientesSinStock = $materialesSinExistencia
            ->filter(fn(array $material): bool => $material['pendiente'] > 0.0001)
            ->values();

        return view('notas_salida.create', [
            'motivo' => $motivo,
            'orden' => $orden,
            'proforma' => $proforma,
            'filas' => $filas,
            'origenListo' => $origenListo,
            'origenNoDisponible' => $origenNoDisponible,
            'materialesOrden' => $materialesOrden,
            'pendientesOrden' => $pendientesOrden,
            'materialesSinExistencia' => $materialesSinExistencia,
            'pendientesSinStock' => $pendientesSinStock,
            'pasosRegistro' => $this->pasosRegistro(),
            'pasoActual' => $origenListo ? 2 : 1,
        ]);
    }

    public function store(
        StoreNotaSalidaRequest $request,
        RegistrarNotaSalidaService $registrarService,
        EvaluarAlertasStockService $alertasService
    ): RedirectResponse {
        $datos = $request->validated();

        $datos['detalles'] = collect($datos['detalles'])
            ->filter(fn(array $detalle) => (float) ($detalle['cantidad'] ?? 0) > 0)
            ->map(fn(array $detalle): array => [
                'inventario_id' => (int) $detalle['inventario_id'],
                'proforma_detalle_id' => ! empty($detalle['proforma_detalle_id'])
                    ? (int) $detalle['proforma_detalle_id']
                    : null,
                'producto_id' => (int) $detalle['producto_id'],
                'repisa_id' => (int) $detalle['repisa_id'],
                'tratamiento' => $detalle['tratamiento'],
                'cantidad' => (float) $detalle['cantidad'],
                'observacion' => $detalle['observacion'] ?? null,
            ])
            ->values()
            ->all();

        $nota = $registrarService->registrarYConfirmar($datos, $request->user());

        $this->evaluarInventariosAfectados($nota, $alertasService, $request);

        return redirect()
            ->route('notas-salida.show', $nota->id)
            ->with(
                'success',
                "Nota de salida {$nota->codigo} confirmada. El inventario fue actualizado."
            );
    }

    public function show(NotaSalida $notaSalida): View
    {
        $notaSalida->load([
            'ordenOperacion.tipoOrden',
            'ordenOperacion.cliente',
            'ordenOperacion.vehiculo',
            'proforma.cliente',
            'registrador',
            'confirmador',
            'anulador',
            'detalles.producto.unidadMedida',
            'detalles.repisa',
            'detalles.proformaDetalle',
            'detalles.reservaMaterial',
        ]);

        return view('notas_salida.show', [
            'nota' => $notaSalida,
            'pasosRegistro' => $this->pasosRegistro(),
        ]);
    }

    public function anular(
        AnularNotaSalidaRequest $request,
        NotaSalida $notaSalida,
        AnularNotaSalidaService $anularService,
        EvaluarAlertasStockService $alertasService
    ): RedirectResponse {
        $nota = $anularService->anular(
            $notaSalida,
            $request->user(),
            $request->validated('motivo_anulacion')
        );

        $this->evaluarInventariosAfectados($nota, $alertasService, $request);

        return redirect()
            ->route('notas-salida.show', $nota->id)
            ->with(
                'success',
                "La nota de salida {$nota->codigo} fue anulada y el stock fue restituido."
            );
    }

    private function filasDisponibles(
        string $motivo,
        ?OrdenOperacion $orden,
        ?Proforma $proforma,
        ?Collection $materialesOrden = null
    ): Collection {
        $query = Inventario::query()
            ->with(['producto.unidadMedida', 'producto.marcaPrincipal', 'repisa'])
            ->where('stock_actual', '>', 0)
            ->whereHas('producto', fn($producto) => $producto->where('estado', true))
            ->whereHas('repisa', fn($repisa) => $repisa->where('estado', true));

        if ($motivo !== 'PROFORMA' || ! $proforma) {
            $materialesOrden = ($materialesOrden ?? collect())->keyBy('producto_id');

            if ($motivo === 'ORDEN_OPERACION' && $orden) {
                $productoIdsPlanificados = $materialesOrden->keys()->filter()->values();
                if ($productoIdsPlanificados->isEmpty()) {
                    return collect();
                }
                $query->whereIn('inventarios.producto_id', $productoIdsPlanificados);
            }

            $inventarios = $query
                ->join('productos as p', 'p.id', '=', 'inventarios.producto_id')
                ->join('repisas as r', 'r.id', '=', 'inventarios.repisa_id')
                ->select('inventarios.*')
                ->orderBy('p.codigo')
                ->orderBy('r.codigo')
                ->get();

            $resumenes = $this->disponibilidad->resumenesProductos(
                $inventarios->pluck('producto_id'),
                $motivo === 'ORDEN_OPERACION' ? $orden?->id : null
            );

            $filas = $inventarios->map(function (Inventario $inventario) use ($resumenes, $materialesOrden): array {
                $resumen = $resumenes->get($inventario->producto_id, []);
                $materialOrden = $materialesOrden->get($inventario->producto_id);

                return [
                    'inventario' => $inventario,
                    'proforma_detalle' => null,
                    'tratamiento' => null,
                    'pendiente_origen' => null,
                    'stock_total_producto' => (float) ($resumen['stock_fisico'] ?? $inventario->stock_actual),
                    'reserva_orden_pendiente' => (float) ($resumen['reservado_orden'] ?? 0),
                    'reservado_global_pendiente' => (float) ($resumen['reservado'] ?? 0),
                    'disponible_libre' => (float) ($resumen['disponible'] ?? $inventario->stock_actual),
                    'necesidad_abastecimiento' => (float) ($resumen['necesidad_abastecimiento'] ?? 0),
                    'herramientas_en_uso' => (float) ($resumen['herramientas_en_uso'] ?? 0),
                    'material_orden' => $materialOrden,
                ];
            });

            if ($motivo === 'ORDEN_OPERACION' && $orden) {
                $filas = $filas
                    ->sortBy(function (array $fila): int {
                        $material = $fila['material_orden'] ?? null;
                        if ($material && ($material['pendiente'] ?? 0) > 0.0001) {
                            return 0;
                        }
                        if ($material) {
                            return 1;
                        }

                        return 2;
                    })
                    ->values();
            }

            return $filas;
        }

        $detalles = $proforma->detalles
            ->filter(fn($detalle) => $detalle->cantidadPendienteSalida() > 0.0001)
            ->values();

        if ($detalles->isEmpty()) {
            return collect();
        }

        $inventarios = $query
            ->whereIn('producto_id', $detalles->pluck('producto_id')->unique())
            ->get()
            ->groupBy('producto_id');

        $resumenes = $this->disponibilidad->resumenesProductos($detalles->pluck('producto_id'));

        return $detalles->flatMap(function ($detalle) use ($inventarios, $resumenes): Collection {
            $tratamiento = $detalle->tratamiento === 'PRESTAMO'
                ? 'PRESTAMO_EXTERNO'
                : 'VENTA_DIRECTA';
            $pendiente = $detalle->cantidadPendienteSalida();
            $resumen = $resumenes->get($detalle->producto_id, []);

            return $inventarios->get($detalle->producto_id, collect())
                ->map(fn(Inventario $inventario): array => [
                    'inventario' => $inventario,
                    'proforma_detalle' => $detalle,
                    'tratamiento' => $tratamiento,
                    'pendiente_origen' => $pendiente,
                    'stock_total_producto' => (float) ($resumen['stock_fisico'] ?? $inventario->stock_actual),
                    'reserva_orden_pendiente' => 0.0,
                    'reservado_global_pendiente' => (float) ($resumen['reservado'] ?? 0),
                    'disponible_libre' => (float) ($resumen['disponible'] ?? $inventario->stock_actual),
                    'necesidad_abastecimiento' => (float) ($resumen['necesidad_abastecimiento'] ?? 0),
                    'herramientas_en_uso' => (float) ($resumen['herramientas_en_uso'] ?? 0),
                ]);
        })->values();
    }

    /**
     * Resumen de planificación y consumo real de materiales para una orden activa.
     * La cantidad entregada se obtiene únicamente de Notas de Salida confirmadas
     * con tratamiento CONSUMO. Las herramientas (USO_TEMPORAL) no forman parte
     * del consumo del material requerido.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function resumenMaterialesOrden(OrdenOperacion $orden): Collection
    {
        $orden->loadMissing('materialesRequeridos.producto.unidadMedida');

        $materiales = $orden->materialesRequeridos
            ->filter(fn($material): bool => (bool) $material->producto_id)
            ->values();

        if ($materiales->isEmpty()) {
            return collect();
        }

        $productoIds = $materiales->pluck('producto_id')->unique()->values();

        $entregados = DB::table('nota_salida_detalles as d')
            ->join('notas_salida as n', 'n.id', '=', 'd.nota_salida_id')
            ->where('n.orden_operacion_id', $orden->id)
            ->where('n.estado', 'CONFIRMADA')
            ->where('d.tratamiento', 'CONSUMO')
            ->whereIn('d.producto_id', $productoIds)
            ->groupBy('d.producto_id')
            ->selectRaw('d.producto_id, COALESCE(SUM(d.cantidad), 0) as entregado')
            ->get()
            ->keyBy('producto_id');

        $disponibilidades = $this->disponibilidad->resumenesProductos($productoIds, $orden->id);

        return $materiales->map(function ($material) use ($entregados, $disponibilidades): array {
            $entregado = round((float) ($entregados->get($material->producto_id)->entregado ?? 0), 3);
            $requerido = round((float) $material->cantidad_requerida, 3);
            $previsto = round((float) ($material->cantidad_prevista ?? $material->cantidad_requerida), 3);
            $resumen = $disponibilidades->get($material->producto_id, []);

            return [
                'material_id' => (int) $material->id,
                'producto_id' => (int) $material->producto_id,
                'producto' => $material->producto,
                'previsto' => $previsto,
                'requerido' => $requerido,
                'entregado' => $entregado,
                'pendiente' => max(0, round($requerido - $entregado, 3)),
                'excedido' => max(0, round($entregado - $requerido, 3)),
                'reserva_pendiente' => round((float) ($resumen['reservado_orden'] ?? 0), 3),
                'stock_fisico' => round((float) ($resumen['stock_fisico'] ?? 0), 3),
                'disponible_libre' => round((float) ($resumen['disponible'] ?? 0), 3),
                'necesidad_abastecimiento' => round((float) ($resumen['necesidad_abastecimiento'] ?? 0), 3),
            ];
        })->keyBy('producto_id');
    }

    private function pasosRegistro(): array
    {
        return [
            [
                'number' => 1,
                'name' => 'Origen de salida',
                'description' => 'Orden, Proforma o uso interno',
                'target' => 'paso-origen',
            ],
            [
                'number' => 2,
                'name' => 'Datos de entrega',
                'description' => 'Fecha y receptor',
                'target' => 'paso-datos',
            ],
            [
                'number' => 3,
                'name' => 'Productos',
                'description' => 'Cantidad y tratamiento',
                'target' => 'paso-productos',
            ],
            [
                'number' => 4,
                'name' => 'Confirmación',
                'description' => 'Descontar stock físico',
                'target' => 'paso-confirmacion',
            ],
        ];
    }

    private function evaluarInventariosAfectados(
        NotaSalida $nota,
        EvaluarAlertasStockService $alertasService,
        Request $request
    ): void {
        $nota->loadMissing('detalles');

        $inventarios = Inventario::query()
            ->where(function ($query) use ($nota): void {
                foreach ($nota->detalles as $detalle) {
                    $query->orWhere(function ($par) use ($detalle): void {
                        $par
                            ->where('producto_id', $detalle->producto_id)
                            ->where('repisa_id', $detalle->repisa_id);
                    });
                }
            })
            ->get()
            ->unique('id');

        foreach ($inventarios as $inventario) {
            $alertasService->evaluarInventario($inventario, $request->user());
        }
    }
}
