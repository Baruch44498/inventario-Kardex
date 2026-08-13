<?php

namespace App\Http\Controllers;

use App\Http\Requests\RechazarSolicitudCompraRequest;
use App\Models\SolicitudCompra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SolicitudCompraController extends Controller
{
    public function index(Request $request): View
    {
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
        }

        return view('solicitudes_compra.index', [
            'solicitudes' => $query->latest('fecha_solicitud')->latest('id')->paginate(15)->withQueryString(),
            'resumen' => [
                'pendientes' => SolicitudCompra::query()->where('estado', 'PENDIENTE')->count(),
                'aprobadas' => SolicitudCompra::query()->where('estado', 'APROBADA')->count(),
                'rechazadas' => SolicitudCompra::query()->where('estado', 'RECHAZADA')->count(),
                'convertidas' => SolicitudCompra::query()->where('estado', 'CONVERTIDA')->count(),
            ],
            'esContabilidad' => $request->user()->tieneRol('CONTABILIDAD') || $request->user()->esAdministrador(),
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
            'esContabilidad' => $request->user()->tieneRol('CONTABILIDAD') || $request->user()->esAdministrador(),
        ]);
    }

    public function aprobar(Request $request, SolicitudCompra $solicitudCompra): RedirectResponse
    {
        if (! $solicitudCompra->estaPendiente()) {
            return back()->with('error', 'Solo una solicitud pendiente puede aprobarse.');
        }

        $solicitudCompra->update([
            'estado' => 'APROBADA',
            'aprobado_por' => $request->user()->id,
            'aprobado_en' => now(),
            'rechazado_por' => null,
            'rechazado_en' => null,
            'motivo_rechazo' => null,
        ]);

        return back()->with(
            'success',
            'Solicitud aprobada. Ya está habilitada como origen de una orden de compra.'
        );
    }

    public function rechazar(
        RechazarSolicitudCompraRequest $request,
        SolicitudCompra $solicitudCompra
    ): RedirectResponse {
        if (! $solicitudCompra->estaPendiente()) {
            return back()->with('error', 'Solo una solicitud pendiente puede rechazarse.');
        }

        DB::transaction(function () use ($request, $solicitudCompra): void {
            $motivo = trim((string) $request->input('motivo_rechazo'));

            $solicitudCompra->update([
                'estado' => 'RECHAZADA',
                'rechazado_por' => $request->user()->id,
                'rechazado_en' => now(),
                'motivo_rechazo' => $motivo,
            ]);

            $solicitudCompra->cotizacion()->update([
                'estado' => 'NO_UTILIZADA',
                'evaluado_por' => $request->user()->id,
                'evaluado_en' => now(),
                'motivo_evaluacion' => "Rechazada por Contabilidad: {$motivo}",
            ]);
        });

        return back()->with('success', 'Solicitud rechazada y cotización marcada como no utilizada.');
    }
}
