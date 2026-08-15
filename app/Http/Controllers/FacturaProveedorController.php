<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularFacturaProveedorRequest;
use App\Http\Requests\StoreFacturaProveedorRequest;
use App\Models\FacturaProveedor;
use App\Models\OrdenCompra;
use App\Services\Compras\RegistrarFacturaProveedorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'estado' => ['nullable', 'in:REGISTRADA,PAGADA,ANULADA'],
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

        return view('facturas_proveedor.index', [
            'facturas' => $facturas,
            'resumen' => $resumen,
            'puedeRegistrar' => $request->user()->puede('ingresos.registrar'),
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
                && $nota->motivo_ingreso === 'COMPRA'
                && ! $nota->factura_proveedor_id)
            ->flatMap(fn($nota) => $nota->detalles->map(
                fn($detalle) => ['nota' => $nota, 'detalle' => $detalle]
            ))
            ->map(function (array $fila) use ($ordenCompra): ?array {
                $nota = $fila['nota'];
                $ingresoDetalle = $fila['detalle'];
                $detalle = $ingresoDetalle->ordenCompraDetalle;
                $pendiente = $ingresoDetalle->cantidadPendienteFacturar();
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

        $modoRecepcion = $filas->isNotEmpty();
        if (! $modoRecepcion) {
            $filas = $ordenCompra->detalles
                ->map(function ($detalle) use ($ordenCompra): ?array {
                    $pendiente = $detalle->cantidadPendienteFacturar();
                    if ($pendiente <= 0.0001) return null;
                    return [
                        'detalle' => $detalle,
                        'nota' => null,
                        'ingreso_detalle' => null,
                        'pendiente' => $pendiente,
                        'costo_total_default' => $detalle->costoUnitarioInventarioDocumento(),
                        'afecto_igv_default' => $detalle->solicitudCompraDetalle?->cotizacionDetalle?->igv_modo !== 'NO_APLICA'
                            && (float) $ordenCompra->impuesto > 0,
                    ];
                })->filter()->values();
        }

        if ($filas->isEmpty()) {
            return redirect()->route('ordenes-compra.show', $ordenCompra)
                ->with('warning', 'La orden ya no tiene cantidades pendientes de facturar.');
        }

        return view('facturas_proveedor.create', [
            'orden' => $ordenCompra,
            'filas' => $filas,
            'modoRecepcion' => $modoRecepcion,
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
            ->with('success', 'Factura registrada y disponible para Contabilidad y recepción de Almacén.');
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
        ]);

        return view('facturas_proveedor.show', [
            'factura' => $facturaProveedor,
            'notasConciliadas' => $facturaProveedor->notasIngresoConciliadas(),
            'conciliacion' => $facturaProveedor->ordenCompra->conciliacionFacturas(),
            'puedeRegistrarIngreso' => $request->user()->puede('ingresos.registrar'),
            'puedeAnular' => $request->user()->puede('ingresos.registrar'),
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
        if ($facturaProveedor->estaAnulada()) {
            return back()->with('warning', 'La factura ya se encuentra anulada.');
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
    }
}
