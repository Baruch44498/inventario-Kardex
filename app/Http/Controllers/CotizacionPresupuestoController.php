<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularPresupuestoCotizacionRequest;
use App\Http\Requests\GuardarPresupuestoCotizacionRequest;
use App\Models\CotizacionCliente;
use App\Models\CotizacionPresupuesto;
use App\Services\Ventas\PresupuestoCotizacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CotizacionPresupuestoController extends Controller
{
    public function __construct(
        private PresupuestoCotizacionService $presupuestos
    ) {}

    public function show(CotizacionCliente $cotizacionCliente): View
    {
        $cotizacionCliente->load([
            'cliente',
            'tipoOrden',
            'presupuestos' => fn($query) => $query
                ->with(['producto.unidadMedida', 'registradoPor', 'actualizadoPor', 'anuladoPor'])
                ->orderByRaw("CASE WHEN estado = 'VIGENTE' THEN 0 ELSE 1 END")
                ->orderBy('tipo_costo')
                ->orderBy('id'),
        ]);

        return view('cotizaciones_cliente.presupuesto', [
            'cotizacion' => $cotizacionCliente,
            'partidas' => $cotizacionCliente->presupuestos,
            'resumen' => $this->presupuestos->resumen($cotizacionCliente->presupuestos),
            'partida' => new CotizacionPresupuesto([
                'cantidad' => 1,
                'unidad' => 'GLOBAL',
                'moneda' => $cotizacionCliente->moneda ?: 'PEN',
                'tipo_cambio' => (float) $cotizacionCliente->tipo_cambio ?: null,
                'carga_social_porcentaje' => 0,
                'igv_modo' => 'NO_APLICA',
                'igv_porcentaje' => 18,
            ]),
        ]);
    }

    public function store(
        GuardarPresupuestoCotizacionRequest $request,
        CotizacionCliente $cotizacionCliente
    ): RedirectResponse {
        $this->presupuestos->registrar(
            $cotizacionCliente,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('cotizaciones-cliente.presupuesto.show', $cotizacionCliente)
            ->with('success', 'Partida agregada al presupuesto interno. Los importes se recalcularon en PEN y USD.');
    }

    public function edit(CotizacionPresupuesto $presupuesto): View|RedirectResponse
    {
        $presupuesto->load(['cotizacionCliente.cliente', 'producto.unidadMedida']);

        if (! $presupuesto->estaVigente() || ! $presupuesto->cotizacionCliente->esEditable()) {
            return redirect()
                ->route(
                    'cotizaciones-cliente.presupuesto.show',
                    $presupuesto->cotizacionCliente
                )
                ->with('error', 'Esta partida ya no puede editarse.');
        }

        return view('cotizaciones_cliente.presupuesto_edit', [
            'cotizacion' => $presupuesto->cotizacionCliente,
            'partida' => $presupuesto,
        ]);
    }

    public function update(
        GuardarPresupuestoCotizacionRequest $request,
        CotizacionPresupuesto $presupuesto
    ): RedirectResponse {
        $presupuesto = $this->presupuestos->actualizar(
            $presupuesto,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route(
                'cotizaciones-cliente.presupuesto.show',
                $presupuesto->cotizacion_cliente_id
            )
            ->with('success', 'Partida actualizada y recalculada.');
    }

    public function anular(
        AnularPresupuestoCotizacionRequest $request,
        CotizacionPresupuesto $presupuesto
    ): RedirectResponse {
        $presupuesto = $this->presupuestos->anular(
            $presupuesto,
            $request->validated('motivo_anulacion'),
            $request->user()
        );

        return redirect()
            ->route(
                'cotizaciones-cliente.presupuesto.show',
                $presupuesto->cotizacion_cliente_id
            )
            ->with('success', 'Partida anulada. Se conserva en el historial y ya no suma al presupuesto.');
    }
}
