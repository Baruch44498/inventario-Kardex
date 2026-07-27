<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNotaIngresoRequest;
use App\Models\FacturaProveedor;
use App\Models\Inventario;
use App\Models\NotaIngreso;
use App\Models\OrdenCompra;
use App\Models\Repisa;
use App\Services\Inventario\EvaluarAlertasStockService;
use App\Services\Inventario\RegistrarNotaIngresoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class NotaIngresoController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:CONFIRMADA,ANULADA,BORRADOR'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $query = NotaIngreso::query()
            ->with([
                'ordenCompra.proveedor',
                'facturaProveedor',
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
                    ->orWhere('numero_guia_remision', 'like', "%{$busqueda}%")
                    ->orWhereHas('ordenCompra', function ($orden) use ($busqueda): void {
                        $orden->where('codigo', 'like', "%{$busqueda}%");
                    })
                    ->orWhereHas('ordenCompra.proveedor', function ($proveedor) use ($busqueda): void {
                        $proveedor
                            ->where('razon_social', 'like', "%{$busqueda}%")
                            ->orWhere('ruc', 'like', "%{$busqueda}%");
                    });
            });
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (! empty($filtros['desde'])) {
            $query->whereDate('fecha_ingreso', '>=', $filtros['desde']);
        }

        if (! empty($filtros['hasta'])) {
            $query->whereDate('fecha_ingreso', '<=', $filtros['hasta']);
        }

        $notas = $query
            ->orderByDesc('fecha_ingreso')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $resumen = NotaIngreso::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                "SUM(CASE WHEN estado = 'CONFIRMADA' THEN 1 ELSE 0 END) as confirmadas"
            )
            ->selectRaw(
                "SUM(CASE WHEN fecha_ingreso = ? AND estado = 'CONFIRMADA' THEN 1 ELSE 0 END) as hoy",
                [now()->toDateString()]
            )
            ->first();

        $valorRecibido = (float) NotaIngreso::query()
            ->join('nota_ingreso_detalles as d', 'd.nota_ingreso_id', '=', 'notas_ingreso.id')
            ->where('notas_ingreso.estado', 'CONFIRMADA')
            ->sum('d.subtotal');

        return view('notas_ingreso.index', compact(
            'notas',
            'resumen',
            'valorRecibido'
        ));
    }

    public function create(Request $request): View
    {
        $ordenes = $this->ordenesDisponibles();
        $ordenSeleccionadaId = (int) old(
            'orden_compra_id',
            $request->integer('orden_compra_id')
        );

        $orden = null;
        $facturas = collect();
        $repisas = collect();
        $ordenNoDisponible = false;

        if ($ordenSeleccionadaId > 0) {
            $orden = OrdenCompra::query()
                ->with([
                    'proveedor',
                    'detalles.producto.unidadMedida',
                ])
                ->whereIn('estado', ['APROBADA', 'PARCIALMENTE_RECIBIDA'])
                ->find($ordenSeleccionadaId);

            if ($orden) {
                $detallesPendientes = $orden->detalles
                    ->filter(fn ($detalle) =>
                        round(
                            (float) $detalle->cantidad_ordenada
                            - (float) $detalle->cantidad_recibida,
                            3
                        ) > 0
                    )
                    ->values();

                if ($detallesPendientes->isEmpty()) {
                    $orden = null;
                    $ordenNoDisponible = true;
                } else {
                    $orden->setRelation('detalles', $detallesPendientes);

                    $facturas = FacturaProveedor::query()
                        ->where('orden_compra_id', $orden->id)
                        ->where('estado', '!=', 'ANULADA')
                        ->orderByDesc('fecha_emision')
                        ->get();

                    $repisas = Repisa::query()
                        ->where('estado', true)
                        ->orderBy('codigo')
                        ->get();
                }
            } else {
                $ordenNoDisponible = true;
            }
        }

        return view('notas_ingreso.create', [
            'ordenes' => $ordenes,
            'orden' => $orden,
            'facturas' => $facturas,
            'repisas' => $repisas,
            'ordenNoDisponible' => $ordenNoDisponible,
            'pasosRegistro' => $this->pasosRegistro(),
            'pasoActual' => $orden ? 2 : 1,
        ]);
    }

    public function store(
        StoreNotaIngresoRequest $request,
        RegistrarNotaIngresoService $registrarService,
        EvaluarAlertasStockService $alertasService
    ): RedirectResponse {
        $datos = $request->validated();

        $datos['detalles'] = collect($datos['detalles'])
            ->filter(fn (array $detalle) => (float) ($detalle['cantidad'] ?? 0) > 0)
            ->map(function (array $detalle): array {
                return [
                    'orden_compra_detalle_id' => (int) $detalle['orden_compra_detalle_id'],
                    'producto_id' => (int) $detalle['producto_id'],
                    'repisa_id' => (int) $detalle['repisa_id'],
                    'cantidad' => (float) $detalle['cantidad'],
                    'costo_unitario' => (float) $detalle['costo_unitario'],
                    'lote' => $detalle['lote'] ?? null,
                    'fecha_vencimiento' => $detalle['fecha_vencimiento'] ?? null,
                    'observacion' => $detalle['observacion'] ?? null,
                ];
            })
            ->values()
            ->all();

        $nota = $registrarService->registrarYConfirmar(
            $datos,
            $request->user()
        );

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

        return redirect()
            ->route('notas-ingreso.show', $nota->id)
            ->with(
                'success',
                "Nota de ingreso {$nota->codigo} confirmada. El inventario fue actualizado."
            );
    }

    public function show(int $notaIngreso): View
    {
        $nota = NotaIngreso::query()
            ->with([
                'ordenCompra.proveedor',
                'facturaProveedor',
                'registrador',
                'confirmador',
                'detalles.producto.unidadMedida',
                'detalles.repisa',
            ])
            ->findOrFail($notaIngreso);

        return view('notas_ingreso.show', [
            'nota' => $nota,
        ]);
    }

    /**
     * El registro se confirma en una sola operación transaccional, pero la
     * captura de información se organiza en cuatro etapas visibles.
     *
     * @return array<int, array{number:int,name:string,description:string,target:string}>
     */
    private function pasosRegistro(): array
    {
        return [
            [
                'number' => 1,
                'name' => 'Orden de compra',
                'description' => 'Seleccionar una orden pendiente',
                'target' => 'paso-orden',
            ],
            [
                'number' => 2,
                'name' => 'Datos de recepción',
                'description' => 'Documento, fecha y guía',
                'target' => 'paso-datos',
            ],
            [
                'number' => 3,
                'name' => 'Productos y ubicación',
                'description' => 'Cantidades, repisas y costos',
                'target' => 'paso-productos',
            ],
            [
                'number' => 4,
                'name' => 'Confirmación',
                'description' => 'Revisar y actualizar inventario',
                'target' => 'paso-confirmacion',
            ],
        ];
    }

    private function ordenesDisponibles(): Collection
    {
        return OrdenCompra::query()
            ->with('proveedor')
            ->whereIn('estado', ['APROBADA', 'PARCIALMENTE_RECIBIDA'])
            ->whereHas('detalles', function ($detalle): void {
                $detalle->whereColumn(
                    'cantidad_recibida',
                    '<',
                    'cantidad_ordenada'
                );
            })
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->get();
    }

}
