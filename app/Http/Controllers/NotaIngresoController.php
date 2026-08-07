<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNotaIngresoRequest;
use App\Models\FacturaProveedor;
use App\Models\Inventario;
use App\Models\NotaIngreso;
use App\Models\NotaIngresoDetalle;
use App\Models\NotaSalida;
use App\Models\OrdenCompra;
use App\Models\Proforma;
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
            'motivo' => ['nullable', 'in:COMPRA,DEVOLUCION_HERRAMIENTA,RETORNO_MATERIAL,REPOSICION_PRESTAMO'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $query = NotaIngreso::query()
            ->with([
                'ordenCompra.proveedor',
                'facturaProveedor',
                'notaSalidaOrigen',
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
                    ->orWhere('numero_guia_remision', 'like', "%{$busqueda}%")
                    ->orWhereHas(
                        'ordenCompra',
                        fn($orden) =>
                        $orden->where('codigo', 'like', "%{$busqueda}%")
                    )
                    ->orWhereHas('ordenCompra.proveedor', function ($proveedor) use ($busqueda): void {
                        $proveedor
                            ->where('razon_social', 'like', "%{$busqueda}%")
                            ->orWhere('ruc', 'like', "%{$busqueda}%");
                    })
                    ->orWhereHas(
                        'notaSalidaOrigen',
                        fn($salida) =>
                        $salida->where('codigo', 'like', "%{$busqueda}%")
                    )
                    ->orWhereHas(
                        'proforma',
                        fn($proforma) =>
                        $proforma->where('codigo', 'like', "%{$busqueda}%")
                    );
            });
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }
        if (! empty($filtros['motivo'])) {
            $query->where('motivo_ingreso', $filtros['motivo']);
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
            ->selectRaw("SUM(CASE WHEN estado = 'CONFIRMADA' THEN 1 ELSE 0 END) as confirmadas")
            ->selectRaw(
                "SUM(CASE WHEN fecha_ingreso = ? AND estado = 'CONFIRMADA' THEN 1 ELSE 0 END) as hoy",
                [now()->toDateString()]
            )
            ->first();

        $valorRecibido = (float) NotaIngreso::query()
            ->join('nota_ingreso_detalles as d', 'd.nota_ingreso_id', '=', 'notas_ingreso.id')
            ->where('notas_ingreso.estado', 'CONFIRMADA')
            ->sum('d.subtotal');

        return view('notas_ingreso.index', compact('notas', 'resumen', 'valorRecibido'));
    }

    public function create(Request $request): View
    {
        $motivo = (string) old(
            'motivo_ingreso',
            $request->query('motivo_ingreso', 'COMPRA')
        );
        if (! in_array($motivo, StoreNotaIngresoRequest::MOTIVOS, true)) {
            $motivo = 'COMPRA';
        }

        $ordenId = (int) old('orden_compra_id', $request->integer('orden_compra_id'));
        $notaSalidaId = (int) old('nota_salida_id', $request->integer('nota_salida_id'));
        $proformaId = (int) old('proforma_id', $request->integer('proforma_id'));

        $orden = null;
        $notaSalida = null;
        $proforma = null;
        $facturas = collect();
        $origenNoDisponible = false;

        if ($motivo === 'COMPRA' && $ordenId > 0) {
            $orden = OrdenCompra::query()
                ->with(['proveedor', 'detalles.producto.unidadMedida'])
                ->whereIn('estado', ['APROBADA', 'PARCIALMENTE_RECIBIDA'])
                ->find($ordenId);
            $origenNoDisponible = ! $orden;

            if ($orden) {
                $facturas = FacturaProveedor::query()
                    ->where('orden_compra_id', $orden->id)
                    ->where('estado', '!=', 'ANULADA')
                    ->orderByDesc('fecha_emision')
                    ->get();
            }
        }

        if (
            in_array($motivo, ['DEVOLUCION_HERRAMIENTA', 'RETORNO_MATERIAL'], true)
            && $notaSalidaId > 0
        ) {
            $notaSalida = NotaSalida::query()
                ->with([
                    'ordenOperacion',
                    'proforma',
                    'detalles.producto.unidadMedida',
                    'detalles.repisa',
                ])
                ->where('estado', 'CONFIRMADA')
                ->find($notaSalidaId);
            $origenNoDisponible = ! $notaSalida;
        }

        if ($motivo === 'REPOSICION_PRESTAMO' && $proformaId > 0) {
            $proforma = Proforma::query()
                ->with([
                    'cliente',
                    'detalles.producto.unidadMedida',
                    'detalles.reposiciones',
                ])
                ->where('estado', '!=', 'ANULADA')
                ->find($proformaId);
            $origenNoDisponible = ! $proforma;
        }

        $origenListo = match ($motivo) {
            'COMPRA' => (bool) $orden,
            'DEVOLUCION_HERRAMIENTA', 'RETORNO_MATERIAL' => (bool) $notaSalida,
            'REPOSICION_PRESTAMO' => (bool) $proforma,
            default => false,
        };

        $filas = $origenListo
            ? $this->filasIngreso($motivo, $orden, $notaSalida, $proforma)
            : collect();

        $repisaIds = collect($request->old('detalles', []))
            ->pluck('repisa_id')
            ->filter()
            ->merge($filas->pluck('repisa_default_id')->filter())
            ->unique()
            ->values();

        $repisasSeleccionadas = Repisa::query()
            ->whereIn('id', $repisaIds)
            ->get()
            ->keyBy('id');

        return view('notas_ingreso.create', [
            'motivo' => $motivo,
            'orden' => $orden,
            'notaSalida' => $notaSalida,
            'proforma' => $proforma,
            'facturas' => $facturas,
            'filas' => $filas,
            'repisasSeleccionadas' => $repisasSeleccionadas,
            'origenListo' => $origenListo,
            'origenNoDisponible' => $origenNoDisponible,
            'pasosRegistro' => $this->pasosRegistro(),
            'pasoActual' => $origenListo ? 2 : 1,
        ]);
    }

    public function store(
        StoreNotaIngresoRequest $request,
        RegistrarNotaIngresoService $registrarService,
        EvaluarAlertasStockService $alertasService
    ): RedirectResponse {
        $datos = $request->validated();

        $datos['detalles'] = collect($datos['detalles'])
            ->filter(fn(array $detalle) => (float) ($detalle['cantidad'] ?? 0) > 0)
            ->map(fn(array $detalle): array => [
                'orden_compra_detalle_id' => ! empty($detalle['orden_compra_detalle_id'])
                    ? (int) $detalle['orden_compra_detalle_id']
                    : null,
                'nota_salida_detalle_id' => ! empty($detalle['nota_salida_detalle_id'])
                    ? (int) $detalle['nota_salida_detalle_id']
                    : null,
                'proforma_detalle_id' => ! empty($detalle['proforma_detalle_id'])
                    ? (int) $detalle['proforma_detalle_id']
                    : null,
                'producto_id' => (int) $detalle['producto_id'],
                'repisa_id' => (int) $detalle['repisa_id'],
                'cantidad' => (float) $detalle['cantidad'],
                'costo_unitario' => isset($detalle['costo_unitario'])
                    ? (float) $detalle['costo_unitario']
                    : null,
                'lote' => $detalle['lote'] ?? null,
                'fecha_vencimiento' => $detalle['fecha_vencimiento'] ?? null,
                'observacion' => $detalle['observacion'] ?? null,
            ])
            ->values()
            ->all();

        $nota = $registrarService->registrarYConfirmar($datos, $request->user());

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
                'notaSalidaOrigen.ordenOperacion',
                'notaSalidaOrigen.proforma',
                'proforma.cliente',
                'registrador',
                'confirmador',
                'detalles.producto.unidadMedida',
                'detalles.repisa',
                'detalles.notaSalidaDetalle',
                'detalles.proformaDetalle',
            ])
            ->findOrFail($notaIngreso);

        return view('notas_ingreso.show', [
            'nota' => $nota,
            'pasosRegistro' => $this->pasosRegistro(),
        ]);
    }

    private function filasIngreso(
        string $motivo,
        ?OrdenCompra $orden,
        ?NotaSalida $notaSalida,
        ?Proforma $proforma
    ): Collection {
        if ($motivo === 'COMPRA' && $orden) {
            return $orden->detalles
                ->map(function ($detalle): ?array {
                    $pendiente = round(
                        (float) $detalle->cantidad_ordenada - (float) $detalle->cantidad_recibida,
                        3
                    );
                    if ($pendiente <= 0) {
                        return null;
                    }

                    return [
                        'tipo' => 'COMPRA',
                        'producto' => $detalle->producto,
                        'pendiente' => $pendiente,
                        'orden_compra_detalle_id' => $detalle->id,
                        'nota_salida_detalle_id' => null,
                        'proforma_detalle_id' => null,
                        'costo_default' => (float) $detalle->precio_unitario,
                        'repisa_default_id' => null,
                    ];
                })
                ->filter()
                ->values();
        }

        if (
            in_array($motivo, ['DEVOLUCION_HERRAMIENTA', 'RETORNO_MATERIAL'], true)
            && $notaSalida
        ) {
            $tratamiento = $motivo === 'DEVOLUCION_HERRAMIENTA'
                ? 'USO_TEMPORAL'
                : 'CONSUMO';

            return $notaSalida->detalles
                ->where('tratamiento', $tratamiento)
                ->map(function ($detalle): ?array {
                    $yaRetornado = (float) NotaIngresoDetalle::query()
                        ->where('nota_salida_detalle_id', $detalle->id)
                        ->whereHas('notaIngreso', fn($query) => $query->where('estado', 'CONFIRMADA'))
                        ->sum('cantidad');
                    $pendiente = max(0, round((float) $detalle->cantidad - $yaRetornado, 3));
                    if ($pendiente <= 0.0001) {
                        return null;
                    }

                    return [
                        'tipo' => 'RETORNO',
                        'producto' => $detalle->producto,
                        'pendiente' => $pendiente,
                        'orden_compra_detalle_id' => null,
                        'nota_salida_detalle_id' => $detalle->id,
                        'proforma_detalle_id' => null,
                        'costo_default' => (float) $detalle->costo_unitario_promedio,
                        'repisa_default_id' => $detalle->repisa_id,
                    ];
                })
                ->filter()
                ->values();
        }

        if ($motivo === 'REPOSICION_PRESTAMO' && $proforma) {
            return $proforma->detalles
                ->where('tratamiento', 'PRESTAMO')
                ->map(function ($detalle): ?array {
                    $pendiente = $detalle->cantidadPendienteReposicion();
                    if ($pendiente <= 0.0001) {
                        return null;
                    }

                    $salida = $detalle->notasSalidaDetalles()
                        ->where('tratamiento', 'PRESTAMO_EXTERNO')
                        ->whereHas('notaSalida', fn($query) => $query->where('estado', 'CONFIRMADA'))
                        ->latest('id')
                        ->first();

                    return [
                        'tipo' => 'REPOSICION',
                        'producto' => $detalle->producto,
                        'pendiente' => $pendiente,
                        'orden_compra_detalle_id' => null,
                        'nota_salida_detalle_id' => null,
                        'proforma_detalle_id' => $detalle->id,
                        'costo_default' => (float) ($salida?->costo_unitario_promedio ?? 0),
                        'repisa_default_id' => $salida?->repisa_id,
                    ];
                })
                ->filter()
                ->values();
        }

        return collect();
    }

    private function pasosRegistro(): array
    {
        return [
            [
                'number' => 1,
                'name' => 'Origen del ingreso',
                'description' => 'Compra, devolución o reposición',
                'target' => 'paso-origen',
            ],
            [
                'number' => 2,
                'name' => 'Datos de recepción',
                'description' => 'Fecha y referencia',
                'target' => 'paso-datos',
            ],
            [
                'number' => 3,
                'name' => 'Productos y ubicación',
                'description' => 'Cantidad y repisa',
                'target' => 'paso-productos',
            ],
            [
                'number' => 4,
                'name' => 'Confirmación',
                'description' => 'Incrementar stock físico',
                'target' => 'paso-confirmacion',
            ],
        ];
    }
}
