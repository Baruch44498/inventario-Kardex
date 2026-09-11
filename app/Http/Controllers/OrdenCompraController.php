<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularOrdenCompraRequest;
use App\Http\Requests\StoreOrdenCompraRequest;
use App\Models\OrdenCompra;
use App\Models\SolicitudCompra;
use App\Services\Compras\AnularOrdenCompraService;
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
            'situacion' => ['nullable', 'in:PENDIENTE,ATRASADA,VENCE_HOY,EN_PLAZO,SIN_FECHA,PARCIAL,RECIBIDA,ANULADA'],
            'origen' => ['nullable', 'in:REQUERIMIENTO,COMPRA_DIRECTA,REGULARIZACION,URGENTE,REPOSICION'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $query = OrdenCompra::query()
            ->with(['proveedor', 'emisor', 'solicitudCompra'])
            ->withCount([
                'detalles',
                'detalles as detalles_pendientes_count' => fn ($detalles) => $detalles
                    ->whereColumn('cantidad_recibida', '<', 'cantidad_ordenada'),
            ]);

        $hoy = today()->toDateString();
        $estadosPendientes = ['APROBADA', 'PARCIALMENTE_RECIBIDA'];

        if (! empty($filtros['q'])) {
            $termino = trim($filtros['q']);
            $query->where(function ($busqueda) use ($termino): void {
                $busqueda
                    ->where('codigo', 'like', "%{$termino}%")
                    ->orWhere('numero_documento_proveedor', 'like', "%{$termino}%")
                    ->orWhereHas('proveedor', fn($proveedor) => $proveedor
                        ->where('ruc', 'like', "%{$termino}%")
                        ->orWhere('razon_social', 'like', "%{$termino}%")
                        ->orWhere('nombre_comercial', 'like', "%{$termino}%"))
                    ->orWhereHas('solicitudCompra.cotizacion.requisicion', fn($requerimiento) => $requerimiento
                        ->where('codigo', 'like', "%{$termino}%"));
            });
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }
        if (! empty($filtros['situacion'])) {
            match ($filtros['situacion']) {
                'PENDIENTE' => $query->whereIn('estado', $estadosPendientes),
                'ATRASADA' => $query
                    ->whereIn('estado', $estadosPendientes)
                    ->whereDate('fecha_entrega_requerida', '<', $hoy),
                'VENCE_HOY' => $query
                    ->whereIn('estado', $estadosPendientes)
                    ->whereDate('fecha_entrega_requerida', $hoy),
                'EN_PLAZO' => $query
                    ->whereIn('estado', $estadosPendientes)
                    ->whereDate('fecha_entrega_requerida', '>', $hoy),
                'SIN_FECHA' => $query
                    ->whereIn('estado', $estadosPendientes)
                    ->whereNull('fecha_entrega_requerida'),
                'PARCIAL' => $query->where('estado', 'PARCIALMENTE_RECIBIDA'),
                'RECIBIDA' => $query->where('estado', 'RECIBIDA'),
                'ANULADA' => $query->where('estado', 'ANULADA'),
            };
        }
        if (! empty($filtros['origen'])) {
            $query->where('origen', $filtros['origen']);
        }
        if (! empty($filtros['desde'])) {
            $query->whereDate('fecha_emision', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $query->whereDate('fecha_emision', '<=', $filtros['hasta']);
        }

        $pendientes = OrdenCompra::query()->whereIn('estado', $estadosPendientes);

        return view('ordenes_compra.index', [
            'ordenes' => $query
                ->orderByRaw(
                    "CASE
                        WHEN estado IN ('APROBADA', 'PARCIALMENTE_RECIBIDA') AND fecha_entrega_requerida < ? THEN 0
                        WHEN estado IN ('APROBADA', 'PARCIALMENTE_RECIBIDA') AND fecha_entrega_requerida = ? THEN 1
                        WHEN estado IN ('APROBADA', 'PARCIALMENTE_RECIBIDA') AND fecha_entrega_requerida > ? THEN 2
                        WHEN estado IN ('APROBADA', 'PARCIALMENTE_RECIBIDA') THEN 3
                        WHEN estado = 'RECIBIDA' THEN 4
                        ELSE 5
                    END",
                    [$hoy, $hoy, $hoy]
                )
                ->orderBy('fecha_entrega_requerida')
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'resumen' => [
                'recepcion' => (clone $pendientes)->count(),
                'atrasadas' => (clone $pendientes)->whereDate('fecha_entrega_requerida', '<', $hoy)->count(),
                'vence_hoy' => (clone $pendientes)->whereDate('fecha_entrega_requerida', $hoy)->count(),
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
            'detalles.facturaProveedorDetalles.facturaProveedor',
            'notasIngreso.detalles',
            'facturasProveedor.detalles',
        ]);

        return view('ordenes_compra.show', [
            'orden' => $ordenCompra,
            'puedeRegistrarIngreso' => $request->user()->puede('ingresos.registrar'),
            'puedeRegistrarFactura' => $request->user()->puede('ingresos.registrar'),
            'puedeVerOrigen' => $request->user()->puedeAlguno('compras.gestionar', 'contabilidad.ver'),
            'puedeAnular' => $request->user()->puede('compras.gestionar'),
            'conciliacionFacturas' => $ordenCompra->conciliacionFacturas(),
        ]);
    }

    public function anular(
        AnularOrdenCompraRequest $request,
        OrdenCompra $ordenCompra,
        AnularOrdenCompraService $servicio
    ): RedirectResponse {
        if (! $ordenCompra->puedeAnularse()) {
            return back()->with('error', 'No puede anularse una orden con recepción, factura o un estado posterior.');
        }

        $resultado = $servicio->ejecutar(
            $ordenCompra,
            $request->user(),
            trim((string) $request->input('motivo_anulacion'))
        );

        $mensaje = 'Orden de compra anulada. Se conserva para auditoría.';
        if ($resultado['requerimiento_reabierto']) {
            $mensaje .= " El requerimiento {$resultado['requerimiento_reabierto']->codigo} volvió a Cotizando para elegir una nueva oferta.";
        }

        return back()->with('success', $mensaje);
    }
}
