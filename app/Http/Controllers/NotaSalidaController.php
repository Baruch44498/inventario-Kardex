<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularNotaSalidaRequest;
use App\Http\Requests\StoreNotaSalidaRequest;
use App\Models\Inventario;
use App\Models\NotaSalida;
use App\Models\OrdenOperacion;
use App\Services\Inventario\AnularNotaSalidaService;
use App\Services\Inventario\EvaluarAlertasStockService;
use App\Services\Inventario\RegistrarNotaSalidaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class NotaSalidaController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:CONFIRMADA,ANULADA,BORRADOR'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $query = NotaSalida::query()
            ->with([
                'ordenOperacion.tipoOrden',
                'ordenOperacion.cliente',
                'ordenOperacion.vehiculo',
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
                    });
            });
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
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
            ->selectRaw(
                "SUM(CASE WHEN estado = 'CONFIRMADA' THEN 1 ELSE 0 END) as confirmadas"
            )
            ->selectRaw(
                "SUM(CASE WHEN estado = 'ANULADA' THEN 1 ELSE 0 END) as anuladas"
            )
            ->selectRaw(
                "SUM(CASE WHEN fecha_salida = ? AND estado = 'CONFIRMADA' THEN 1 ELSE 0 END) as hoy",
                [now()->toDateString()]
            )
            ->first();

        $valorEntregado = (float) NotaSalida::query()
            ->join('nota_salida_detalles as d', 'd.nota_salida_id', '=', 'notas_salida.id')
            ->where('notas_salida.estado', 'CONFIRMADA')
            ->sum('d.subtotal');

        return view('notas_salida.index', compact(
            'notas',
            'resumen',
            'valorEntregado'
        ));
    }

    public function create(Request $request): View
    {
        $hayOrdenesDisponibles = $this->consultaOrdenesDisponibles()->exists();
        $ordenSeleccionadaId = (int) old(
            'orden_operacion_id',
            $request->integer('orden_operacion_id')
        );

        $orden = null;
        $inventarios = collect();
        $ordenNoDisponible = false;

        if ($ordenSeleccionadaId > 0) {
            $orden = OrdenOperacion::query()
                ->with([
                    'tipoOrden',
                    'cliente',
                    'vehiculo',
                ])
                ->whereNotIn('estado', ['ANULADA', 'CERRADA'])
                ->find($ordenSeleccionadaId);

            if ($orden) {
                $inventarios = Inventario::query()
                    ->with([
                        'producto.unidadMedida',
                        'producto.marcaPrincipal',
                        'repisa',
                    ])
                    ->where('stock_actual', '>', 0)
                    ->whereHas('producto', fn($producto) => $producto->where('estado', true))
                    ->whereHas('repisa', fn($repisa) => $repisa->where('estado', true))
                    ->join('productos as p', 'p.id', '=', 'inventarios.producto_id')
                    ->join('repisas as r', 'r.id', '=', 'inventarios.repisa_id')
                    ->select('inventarios.*')
                    ->orderBy('p.codigo')
                    ->orderBy('r.codigo')
                    ->get();
            } else {
                $ordenNoDisponible = true;
            }
        }

        return view('notas_salida.create', [
            'orden' => $orden,
            'inventarios' => $inventarios,
            'hayOrdenesDisponibles' => $hayOrdenesDisponibles,
            'ordenNoDisponible' => $ordenNoDisponible,
            'pasosRegistro' => $this->pasosRegistro(),
            'pasoActual' => $orden ? 2 : 1,
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
            ->map(function (array $detalle): array {
                return [
                    'producto_id' => (int) $detalle['producto_id'],
                    'repisa_id' => (int) $detalle['repisa_id'],
                    'cantidad' => (float) $detalle['cantidad'],
                    'observacion' => $detalle['observacion'] ?? null,
                ];
            })
            ->values()
            ->all();

        $nota = $registrarService->registrarYConfirmar(
            $datos,
            $request->user()
        );

        $this->evaluarInventariosAfectados(
            $nota,
            $alertasService,
            $request
        );

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
            'registrador',
            'confirmador',
            'anulador',
            'detalles.producto.unidadMedida',
            'detalles.repisa',
        ]);

        return view('notas_salida.show', [
            'nota' => $notaSalida,
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

        $this->evaluarInventariosAfectados(
            $nota,
            $alertasService,
            $request
        );

        return redirect()
            ->route('notas-salida.show', $nota->id)
            ->with(
                'success',
                "La nota de salida {$nota->codigo} fue anulada y el stock fue restituido."
            );
    }

    /**
     * @return array<int, array{number:int,name:string,description:string,target:string}>
     */
    private function pasosRegistro(): array
    {
        return [
            [
                'number' => 1,
                'name' => 'Orden de operación',
                'description' => 'Seleccionar el trabajo asociado',
                'target' => 'paso-orden',
            ],
            [
                'number' => 2,
                'name' => 'Datos de entrega',
                'description' => 'Documento, fecha y receptor',
                'target' => 'paso-datos',
            ],
            [
                'number' => 3,
                'name' => 'Productos y cantidades',
                'description' => 'Seleccionar existencias a entregar',
                'target' => 'paso-productos',
            ],
            [
                'number' => 4,
                'name' => 'Confirmación',
                'description' => 'Revisar y descontar inventario',
                'target' => 'paso-confirmacion',
            ],
        ];
    }

    private function consultaOrdenesDisponibles(): Builder
    {
        return OrdenOperacion::query()
            ->whereNotIn('estado', ['ANULADA', 'CERRADA'])
            ->orderByDesc('fecha_apertura')
            ->orderByDesc('id');
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
            $alertasService->evaluarInventario(
                $inventario,
                $request->user()
            );
        }
    }
}
