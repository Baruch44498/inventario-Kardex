<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCompra;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SolicitudCompraController extends Controller
{
    public function index(Request $request): View
    {
        $esContabilidad = $request->user()->tieneRol('CONTABILIDAD');
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:PENDIENTE,APROBADA,RECHAZADA,CONVERTIDA,ANULADA'],
        ]);

        $query = SolicitudCompra::query()
            ->with(['cotizacion.proveedor', 'solicitante', 'aprobador', 'rechazador'])
            ->withCount('detalles');

        if (! empty($filtros['q'])) {
            $termino = trim($filtros['q']);
            $query->where(function ($busqueda) use ($termino): void {
                $busqueda
                    ->where('codigo', 'like', "%{$termino}%")
                    ->orWhereHas('cotizacion', fn($cotizacion) => $cotizacion
                        ->where('codigo', 'like', "%{$termino}%")
                        ->orWhere('numero_documento', 'like', "%{$termino}%")
                        ->orWhereHas('proveedor', fn($proveedor) => $proveedor
                            ->where('razon_social', 'like', "%{$termino}%")
                            ->orWhere('nombre_comercial', 'like', "%{$termino}%")));
            });
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        } elseif ($esContabilidad) {
            $query->where('estado', 'CONVERTIDA');
        }

        return view('solicitudes_compra.index', [
            'solicitudes' => $query->latest('fecha_solicitud')->latest('id')->paginate(15)->withQueryString(),
            'resumen' => [
                'pendientes' => SolicitudCompra::query()->where('estado', 'PENDIENTE')->count(),
                'aprobadas' => SolicitudCompra::query()->where('estado', 'APROBADA')->count(),
                'rechazadas' => SolicitudCompra::query()->where('estado', 'RECHAZADA')->count(),
                'convertidas' => SolicitudCompra::query()->where('estado', 'CONVERTIDA')->count(),
            ],
            'esContabilidad' => $esContabilidad,
        ]);
    }

    public function show(Request $request, SolicitudCompra $solicitudCompra): View
    {
        $solicitudCompra->load([
            'cotizacion.proveedor',
            'cotizacion.requisicion',
            'cotizacion.registrador',
            'cotizacion.importacionAsistida',
            'solicitante',
            'aprobador',
            'rechazador',
            'detalles.producto.unidadMedida',
            'detalles.cotizacionDetalle',
            'ordenCompra',
        ]);

        return view('solicitudes_compra.show', [
            'solicitud' => $solicitudCompra,
            'esContabilidad' => $request->user()->tieneRol('CONTABILIDAD'),
            'puedeGestionarCompras' => $request->user()->puede('compras.gestionar'),
        ]);
    }
}
