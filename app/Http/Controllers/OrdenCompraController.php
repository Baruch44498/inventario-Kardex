<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularOrdenCompraRequest;
use App\Http\Requests\StoreOrdenCompraRequest;
use App\Models\OrdenCompra;
use App\Models\SolicitudCompra;
use App\Services\Compras\CrearOrdenCompraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrdenCompraController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:APROBADA,PARCIALMENTE_RECIBIDA,RECIBIDA,ANULADA'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $query = OrdenCompra::query()
            ->with(['proveedor', 'emisor', 'solicitudCompra'])
            ->withCount('detalles');

        if (! empty($filtros['q'])) {
            $termino = trim($filtros['q']);
            $query->where(function ($busqueda) use ($termino): void {
                $busqueda
                    ->where('codigo', 'like', "%{$termino}%")
                    ->orWhere('numero_documento_proveedor', 'like', "%{$termino}%")
                    ->orWhereHas('proveedor', fn($proveedor) => $proveedor
                        ->where('ruc', 'like', "%{$termino}%")
                        ->orWhere('razon_social', 'like', "%{$termino}%")
                        ->orWhere('nombre_comercial', 'like', "%{$termino}%"));
            });
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }
        if (! empty($filtros['desde'])) {
            $query->whereDate('fecha_emision', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $query->whereDate('fecha_emision', '<=', $filtros['hasta']);
        }

        return view('ordenes_compra.index', [
            'ordenes' => $query->latest('fecha_emision')->latest('id')->paginate(15)->withQueryString(),
            'resumen' => [
                'recepcion' => OrdenCompra::query()->whereIn('estado', ['APROBADA', 'PARCIALMENTE_RECIBIDA'])->count(),
                'parciales' => OrdenCompra::query()->where('estado', 'PARCIALMENTE_RECIBIDA')->count(),
                'recibidas' => OrdenCompra::query()->where('estado', 'RECIBIDA')->count(),
                'anuladas' => OrdenCompra::query()->where('estado', 'ANULADA')->count(),
            ],
            'puedeGestionarCompras' => $request->user()->puede('compras.gestionar'),
            'puedeRegistrarIngreso' => $request->user()->puede('ingresos.registrar'),
        ]);
    }

    public function create(SolicitudCompra $solicitudCompra): View|RedirectResponse
    {
        $solicitudCompra->load([
            'cotizacion.proveedor',
            'cotizacion.requisicion',
            'aprobador',
            'detalles.producto.unidadMedida',
            'ordenCompra',
        ]);

        if (! $solicitudCompra->puedeConvertirseEnOrden()) {
            return redirect()
                ->route('solicitudes-compra.show', $solicitudCompra)
                ->with('error', 'Esta solicitud no está disponible para generar una orden de compra.');
        }

        return view('ordenes_compra.create', ['solicitud' => $solicitudCompra]);
    }

    public function store(
        StoreOrdenCompraRequest $request,
        CrearOrdenCompraService $servicio
    ): RedirectResponse {
        $orden = $servicio->crear($request->validated(), $request->user());

        return redirect()
            ->route('ordenes-compra.show', $orden)
            ->with('success', 'Orden de compra emitida y habilitada para recepción.');
    }

    public function show(Request $request, OrdenCompra $ordenCompra): View
    {
        $ordenCompra->load([
            'proveedor',
            'emisor',
            'aprobador',
            'anulador',
            'solicitudCompra.cotizacion',
            'solicitudCompra.cotizacion.importacionAsistida',
            'detalles.producto.unidadMedida',
            'notasIngreso.detalles',
            'facturasProveedor',
        ]);

        return view('ordenes_compra.show', [
            'orden' => $ordenCompra,
            'puedeRegistrarIngreso' => $request->user()->puede('ingresos.registrar'),
            'puedeVerOrigen' => $request->user()->puedeAlguno('compras.gestionar', 'contabilidad.ver'),
            'puedeAnular' => $request->user()->puede('compras.gestionar'),
        ]);
    }

    public function anular(
        AnularOrdenCompraRequest $request,
        OrdenCompra $ordenCompra
    ): RedirectResponse {
        if (! $ordenCompra->puedeAnularse()) {
            return back()->with('error', 'No puede anularse una orden con recepción, factura o un estado posterior.');
        }

        $ordenCompra->update([
            'estado' => 'ANULADA',
            'anulado_por' => $request->user()->id,
            'anulado_en' => now(),
            'motivo_anulacion' => trim((string) $request->input('motivo_anulacion')),
        ]);

        return back()->with('success', 'Orden de compra anulada. Se conserva para auditoría.');
    }
}
