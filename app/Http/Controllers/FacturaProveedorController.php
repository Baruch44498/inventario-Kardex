<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularFacturaProveedorRequest;
use App\Http\Requests\StoreFacturaProveedorRequest;
use App\Models\FacturaProveedor;
use App\Models\OrdenCompra;
use App\Services\Compras\RegistrarFacturaProveedorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class FacturaProveedorController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:REGISTRADA,PARCIAL,PAGADA,ANULADA'],
            'moneda' => ['nullable', 'in:PEN,USD'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $query = FacturaProveedor::query()
            ->with(['proveedor', 'ordenCompra', 'registrador'])
            ->withCount('detalles')
            ->withCount('notasIngreso')
            ->withCount([
                'detalles as detalles_con_recepcion_count' => fn($detalle) => $detalle
                    ->whereNotNull('nota_ingreso_detalle_id'),
            ]);

        if (! empty($filtros['q'])) {
            $busqueda = trim($filtros['q']);
            $query->where(function ($subquery) use ($busqueda): void {
                $subquery
                    ->where('serie', 'like', "%{$busqueda}%")
                    ->orWhere('numero', 'like', "%{$busqueda}%")
                    ->orWhereHas('proveedor', fn($proveedor) => $proveedor
                        ->where('razon_social', 'like', "%{$busqueda}%")
                        ->orWhere('ruc', 'like', "%{$busqueda}%"))
                    ->orWhereHas('ordenCompra', fn($orden) => $orden
                        ->where('codigo', 'like', "%{$busqueda}%"));
            });
        }
        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }
        if (! empty($filtros['moneda'])) {
            $query->where('moneda', $filtros['moneda']);
        }
        if (! empty($filtros['desde'])) {
            $query->whereDate('fecha_emision', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $query->whereDate('fecha_emision', '<=', $filtros['hasta']);
        }

        $facturas = $query
            ->latest('fecha_emision')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $activas = FacturaProveedor::query()->where('estado', '!=', 'ANULADA')->get();
        $resumen = [
            'registradas' => $activas->count(),
            'con_recepcion' => FacturaProveedor::query()
                ->where('estado', '!=', 'ANULADA')
                ->where(function ($factura): void {
                    $factura->has('notasIngreso')
                        ->orWhereHas('detalles', fn($detalle) => $detalle->whereNotNull('nota_ingreso_detalle_id'));
                })
                ->count(),
            'base_soles' => $activas->sum(fn(FacturaProveedor $factura): float => $factura->subtotalEnSoles()),
            'credito_fiscal_soles' => $activas->sum(fn(FacturaProveedor $factura): float => $factura->creditoFiscalEnSoles()),
            'total_soles' => $activas->sum(fn(FacturaProveedor $factura): float => $factura->totalEnSoles()),
        ];

        // Solo las recepciones confirmadas con saldo en la nota y en la OC
        // pueden generar una nueva factura.
        $candidatos = DB::table('nota_ingreso_detalles as d')
            ->join('notas_ingreso as n', 'n.id', '=', 'd.nota_ingreso_id')
            ->join('orden_compra_detalles as od', 'od.id', '=', 'd.orden_compra_detalle_id')
            ->join('ordenes_compra as oc', 'oc.id', '=', 'n.orden_compra_id')
            ->where('n.estado', 'CONFIRMADA')
            ->where('n.motivo_ingreso', 'COMPRA')
            ->where('d.afecta_stock', true)
            ->where('oc.estado', '!=', 'ANULADA')
            ->whereRaw("d.cantidad > COALESCE((SELECT SUM(fd.cantidad) FROM factura_proveedor_detalles fd JOIN facturas_proveedor f ON f.id = fd.factura_proveedor_id WHERE fd.nota_ingreso_detalle_id = d.id AND f.estado != 'ANULADA'), 0) + 0.0001")
            ->whereRaw("od.cantidad_ordenada > COALESCE((SELECT SUM(fd.cantidad) FROM factura_proveedor_detalles fd JOIN facturas_proveedor f ON f.id = fd.factura_proveedor_id WHERE fd.orden_compra_detalle_id = od.id AND f.estado != 'ANULADA'), 0) + 0.0001")
            ->select('oc.id')->selectRaw('MAX(n.fecha_ingreso) as ultima_recepcion')
            ->groupBy('oc.id')
            ->orderByDesc('ultima_recepcion')->orderByDesc('oc.id')
            ->paginate(12, ['oc.id'], 'pendientes_page')->withQueryString();
        $filasPendientes = collect($candidatos->items());
        $ordenesPendientes = OrdenCompra::query()
            ->with([
                'proveedor', 'detalles.producto.unidadMedida',
                'notasIngreso' => fn($query) => $query->where('estado', 'CONFIRMADA')->where('motivo_ingreso', 'COMPRA'),
                'notasIngreso.detalles.facturaProveedorDetalles.facturaProveedor',
            ])
            ->whereIn('id', $filasPendientes->pluck('id'))->get()->keyBy('id');
        $pendientesFacturar = $filasPendientes->map(function ($fila) use ($ordenesPendientes): ?array {
            $orden = $ordenesPendientes->get($fila->id);
            if (! $orden) {
                return null;
            }
            $porLinea = $orden->notasIngreso->flatMap->detalles
                ->filter(fn($detalle): bool => $detalle->afecta_stock)
                ->groupBy('orden_compra_detalle_id');
            $pendientes = $porLinea->map(function ($detalles, $id) use ($orden): array {
                $linea = $orden->detalles->firstWhere('id', (int) $id);
                $cantidad = min(
                    (float) $detalles->sum(fn($detalle): float => $detalle->cantidadPendienteFacturar()),
                    $linea?->cantidadPendienteFacturar() ?? 0
                );

                return ['cantidad' => $cantidad, 'unidad' => $linea?->producto?->unidadMedida?->codigo ?? 'UND'];
            })->filter(fn(array $linea): bool => $linea['cantidad'] > 0.0001);

            return $pendientes->isEmpty() ? null : [
                'orden' => $orden,
                'cantidad' => (float) $pendientes->sum('cantidad'),
                'lineas' => $pendientes->count(),
                'unidades' => $pendientes->pluck('unidad')->unique()->values(),
                'ultima_recepcion' => $fila->ultima_recepcion,
            ];
        })->filter()->values();

        return view('facturas_proveedor.index', [
            'facturas' => $facturas,
            'resumen' => $resumen,
            'puedeRegistrar' => $request->user()->puede('ingresos.registrar'),
            'pendientesFacturar' => $pendientesFacturar,
            'paginasPendientes' => $candidatos,
        ]);
    }

    public function create(OrdenCompra $ordenCompra): View|RedirectResponse
    {
        $ordenCompra->load([
            'proveedor',
            'detalles.producto.unidadMedida',
            'detalles.solicitudCompraDetalle.cotizacionDetalle.cotizacion',
            'facturasProveedor.detalles',
            'notasIngreso.detalles.ordenCompraDetalle.solicitudCompraDetalle.cotizacionDetalle.cotizacion',
            'notasIngreso.detalles.producto.unidadMedida',
            'notasIngreso.detalles.facturaProveedorDetalles.facturaProveedor',
        ]);

        if ($ordenCompra->estaAnulada()) {
            return redirect()->route('ordenes-compra.show', $ordenCompra)
                ->with('error', 'Una orden anulada no admite facturas.');
        }

        $filas = $ordenCompra->notasIngreso
            ->filter(fn($nota) => $nota->estaConfirmada()
                && $nota->motivo_ingreso === 'COMPRA')
            ->flatMap(fn($nota) => $nota->detalles->map(
                fn($detalle) => ['nota' => $nota, 'detalle' => $detalle]
            ))
            ->map(function (array $fila) use ($ordenCompra): ?array {
                $nota = $fila['nota'];
                $ingresoDetalle = $fila['detalle'];
                $detalle = $ingresoDetalle->ordenCompraDetalle;
                if (! $detalle) {
                    return null;
                }

                $pendiente = min(
                    $ingresoDetalle->cantidadPendienteFacturar(),
                    $detalle->cantidadPendienteFacturar()
                );
                if ($pendiente <= 0.0001) {
                    return null;
                }

                return [
                    'detalle' => $detalle,
                    'nota' => $nota,
                    'ingreso_detalle' => $ingresoDetalle,
                    'pendiente' => $pendiente,
                    'costo_total_default' => $detalle->costoUnitarioInventarioDocumento(),
                    'afecto_igv_default' => $detalle->solicitudCompraDetalle?->cotizacionDetalle?->igv_modo !== 'NO_APLICA'
                        && (float) $ordenCompra->impuesto > 0,
                ];
            })
            ->filter()
            ->values();

        if ($filas->isEmpty()) {
            $tieneRecepciones = $ordenCompra->notasIngreso
                ->contains(fn($nota): bool => $nota->estaConfirmada() && $nota->motivo_ingreso === 'COMPRA');

            return redirect()->route('ordenes-compra.show', $ordenCompra)
                ->with('warning', $tieneRecepciones
                    ? 'Las recepciones confirmadas de esta orden ya no tienen cantidades pendientes de facturar.'
                    : 'Primero confirma una Nota de Ingreso de compra. La factura se registra únicamente sobre mercadería recibida.');
        }

        return view('facturas_proveedor.create', [
            'orden' => $ordenCompra,
            'filas' => $filas,
        ]);
    }

    public function store(
        StoreFacturaProveedorRequest $request,
        RegistrarFacturaProveedorService $registrar
    ): RedirectResponse {
        $archivo = $request->file('archivo_original');
        $extension = strtolower($archivo->getClientOriginalExtension() ?: 'bin');
        $ruta = $archivo->storeAs(
            'facturas-proveedor/' . now()->format('Y'),
            Str::uuid()->toString() . '.' . $extension,
            'local'
        );

        try {
            $factura = $registrar->registrar($request->validated(), [
                'path' => $ruta,
                'nombre' => $archivo->getClientOriginalName(),
                'mime' => $archivo->getMimeType() ?: 'application/octet-stream',
                'hash' => hash_file('sha256', Storage::disk('local')->path($ruta)),
            ], $request->user());
        } catch (Throwable $error) {
            Storage::disk('local')->delete($ruta);
            throw $error;
        }

        return redirect()
            ->route('facturas-proveedor.show', $factura)
            ->with('success', 'Factura registrada con los importes autorizados de la OC y conciliada con la recepción seleccionada.');
    }

    public function show(Request $request, FacturaProveedor $facturaProveedor): View
    {
        $facturaProveedor->load([
            'ordenCompra.proveedor',
            'ordenCompra.detalles.producto.unidadMedida',
            'ordenCompra.notasIngreso.detalles',
            'proveedor',
            'registrador',
            'anulador',
            'detalles.producto.unidadMedida',
            'detalles.ordenCompraDetalle',
            'detalles.notaIngresoDetalle.notaIngreso.detalles',
            'notasIngreso.detalles',
            'pagos.registrador',
            'pagos.anulador',
            'pagosVigentes',
        ]);

        return view('facturas_proveedor.show', [
            'factura' => $facturaProveedor,
            'notasConciliadas' => $facturaProveedor->notasIngresoConciliadas(),
            'conciliacion' => $facturaProveedor->ordenCompra->conciliacionFacturas(),
            'puedeRegistrarIngreso' => $request->user()->puede('ingresos.registrar'),
            'puedeAnular' => $request->user()->puede('ingresos.registrar'),
            'puedeRegistrarPago' => $request->user()->puede('contabilidad.registrar_pagos'),
        ]);
    }

    public function documentoOriginal(FacturaProveedor $facturaProveedor): BinaryFileResponse
    {
        abort_unless($facturaProveedor->tieneArchivoOriginal(), 404);
        abort_unless(Storage::disk('local')->exists($facturaProveedor->archivo_original_path), 404);

        return response()->download(
            Storage::disk('local')->path($facturaProveedor->archivo_original_path),
            $facturaProveedor->archivo_original_nombre ?: "factura-{$facturaProveedor->id}"
        );
    }

    public function anular(
        AnularFacturaProveedorRequest $request,
        FacturaProveedor $facturaProveedor
    ): RedirectResponse {
        return DB::transaction(function () use ($request, $facturaProveedor): RedirectResponse {
            $facturaProveedor = FacturaProveedor::query()->lockForUpdate()->findOrFail($facturaProveedor->id);
            if ($facturaProveedor->estaAnulada()) {
                return back()->with('warning', 'La factura ya se encuentra anulada.');
            }
            if ($facturaProveedor->pagosVigentes()->exists()) {
                return back()->with('error', 'Primero anula los pagos de la factura desde Contabilidad.');
            }
            if ($facturaProveedor->tieneRecepcionFisica()) {
                return back()->with('error', 'No puede anularse una factura vinculada a una recepción confirmada.');
            }

            $facturaProveedor->update([
                'estado' => 'ANULADA',
                'anulado_por' => $request->user()->id,
                'anulado_en' => now(),
                'motivo_anulacion' => trim((string) $request->input('motivo_anulacion')),
            ]);

            return back()->with('success', 'Factura anulada. El documento original se conserva para auditoría.');
        });
    }
}
