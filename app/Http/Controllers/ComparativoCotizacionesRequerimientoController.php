<?php

namespace App\Http\Controllers;

use App\Http\Requests\ComprarSeleccionCotizacionesRequest;
use App\Models\Cotizacion;
use App\Models\OrdenCompra;
use App\Models\Requisicion;
use App\Services\Compras\AprobarCompraYGenerarOrdenService;
use App\Services\Compras\CompararCotizacionesRequerimientoService;
use App\Services\Compras\HistorialRequerimientoCompraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ComparativoCotizacionesRequerimientoController extends Controller
{
    public function __construct(
        private CompararCotizacionesRequerimientoService $comparador,
        private AprobarCompraYGenerarOrdenService $aprobacion,
        private HistorialRequerimientoCompraService $historial
    ) {}

    public function show(Request $request, Requisicion $requerimientoCompra): View
    {
        abort_unless(
            $request->user()->puede('compras.gestionar') || $request->user()->esAdministrador(),
            403
        );
        abort_if($requerimientoCompra->estado === 'ANULADA', 404);

        return view('requerimientos_compra.comparativo', [
            'requerimiento' => $requerimientoCompra,
            'comparativo' => $this->comparador->construir($requerimientoCompra),
            'puedeComprar' => $requerimientoCompra->estado === 'COTIZANDO',
        ]);
    }

    public function store(
        ComprarSeleccionCotizacionesRequest $request,
        Requisicion $requerimientoCompra
    ): RedirectResponse {
        abort_unless(
            $request->user()->puede('compras.gestionar') || $request->user()->esAdministrador(),
            403
        );

        $data = $request->validated();

        $requerimiento = $requerimientoCompra->fresh();
        if ($requerimiento->estado !== 'COTIZANDO') {
            throw ValidationException::withMessages([
                'estado' => 'El requerimiento debe estar en Cotizando para elegir ofertas y generar órdenes.',
            ]);
        }

        $requerimiento->load('detalles');
        $seleccionados = $this->comparador->validarSeleccion(
            $requerimiento,
            $data['selecciones']
        );

        /** @var Collection<int, OrdenCompra> $ordenes */
        $ordenes = collect();
        foreach ($seleccionados->groupBy('cotizacion_id') as $detalles) {
            /** @var Cotizacion $cotizacion */
            $cotizacion = $detalles->first()->cotizacion;
            $ordenes->push($this->aprobacion->ejecutar($cotizacion, [
                'detalle_ids' => $detalles->pluck('id')->all(),
                'es_compra_directa' => false,
                'fecha_emision' => $data['fecha_emision'],
                'fecha_entrega_requerida' => $data['fecha_entrega_requerida'] ?? null,
                'numero_documento_proveedor' => $cotizacion->numero_documento,
                'condiciones_pago' => $cotizacion->condiciones_pago,
                'condiciones_entrega' => $cotizacion->condiciones_entrega,
                'descripcion' => "Selección comparativa del requerimiento {$requerimiento->codigo}.",
                'observacion' => $data['observacion'] ?? null,
            ], $request->user()));
        }

        $compradas = $this->comparador->comprasPorLinea($requerimiento->detalles->pluck('id'));
        if ($compradas->count() === $requerimiento->detalles->count()) {
            $this->historial->cambiarEstado(
                $requerimiento,
                ['COTIZANDO'],
                'ATENDIDA',
                $request->user(),
                'Todas las líneas quedaron cubiertas por órdenes de compra.',
                [
                    'atendido_por' => $request->user()->id,
                    'atendido_en' => now(),
                ]
            );
        }

        return redirect()
            ->route('requerimientos-compra.comparativo', $requerimientoCompra)
            ->with(
                'success',
                'Se generaron '.$ordenes->count().' orden(es) de compra: '
                    .$ordenes->pluck('codigo')->implode(', ').'.'
            );
    }
}
