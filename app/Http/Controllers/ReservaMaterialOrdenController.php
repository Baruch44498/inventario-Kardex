<?php

namespace App\Http\Controllers;

use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\ReservaMaterialOrden;
use App\Services\Inventario\DisponibilidadMaterialService;
use App\Services\Inventario\ReservaMaterialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReservaMaterialOrdenController extends Controller
{
    public function store(
        Request $request,
        OrdenOperacion $ordenOperacion,
        ReservaMaterialService $reservas,
        DisponibilidadMaterialService $disponibilidad
    ): RedirectResponse {
        $datos = $request->validate([
            'producto_id' => [
                'required',
                'integer',
                Rule::exists('productos', 'id')->where('estado', true),
            ],
            'cantidad' => ['required', 'numeric', 'gt:0', 'max:999999999.999'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $producto = Producto::query()->findOrFail((int) $datos['producto_id']);
        $reservas->agregar(
            $ordenOperacion,
            $producto->id,
            (float) $datos['cantidad'],
            $datos['observacion'] ?? null,
            $request->user()
        );

        $resumen = $disponibilidad->resumenProducto($producto->id, $ordenOperacion->id);
        $mensaje = "Reserva de {$producto->codigo} registrada para {$ordenOperacion->codigo_orden}.";

        $respuesta = redirect()
            ->route('ordenes-operacion.show', $ordenOperacion->id)
            ->with('success', $mensaje);

        if ($resumen['necesidad_abastecimiento'] > 0.0001) {
            $respuesta->with(
                'warning',
                'La reserva deja una necesidad proyectada de abastecimiento de '
                . number_format($resumen['necesidad_abastecimiento'], 2)
                . '. No se bloqueó la reserva.'
            );
        }

        return $respuesta;
    }

    public function liberar(
        Request $request,
        ReservaMaterialOrden $reservaMaterial,
        ReservaMaterialService $reservas
    ): RedirectResponse {
        $reservaMaterial->load('ordenOperacion', 'producto');

        if (in_array($reservaMaterial->ordenOperacion?->estado, ['CERRADA', 'ANULADA'], true)) {
            return back()->with('error', 'La orden ya está cerrada o anulada.');
        }

        if ($reservaMaterial->cantidadPendiente() <= 0.0001) {
            return back()->with('info', 'La reserva ya no tiene saldo pendiente por liberar.');
        }

        $reservas->liberar($reservaMaterial, $request->user());

        return back()->with(
            'success',
            'Se liberó el saldo pendiente de la reserva de '
            . ($reservaMaterial->producto?->codigo ?? 'material') . '.'
        );
    }
}
