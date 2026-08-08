<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequeridoOrden;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Services\Ordenes\MaterialRequeridoOrdenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MaterialRequeridoOrdenController extends Controller
{
    public function store(
        Request $request,
        OrdenOperacion $ordenOperacion,
        MaterialRequeridoOrdenService $materiales
    ): RedirectResponse {
        $datos = $request->validate([
            'producto_id' => [
                'required',
                'integer',
                Rule::exists('productos', 'id')->where('estado', true),
            ],
            'cantidad' => ['required', 'numeric', 'gt:0', 'max:999999999.999'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $producto = Producto::query()->findOrFail((int) $datos['producto_id']);
        $existia = MaterialRequeridoOrden::query()
            ->where('orden_operacion_id', $ordenOperacion->id)
            ->where('producto_id', $producto->id)
            ->exists();

        $materiales->agregar(
            $ordenOperacion,
            $producto->id,
            (float) $datos['cantidad'],
            $datos['motivo'] ?? null,
            $request->user()
        );

        return redirect()
            ->to(route('ordenes-operacion.show', $ordenOperacion->id).'#materiales-requeridos')
            ->with(
                'success',
                $existia
                    ? "Se añadieron {$this->cantidadVisible((float) $datos['cantidad'])} al requerimiento de {$producto->codigo}."
                    : "Material {$producto->codigo} agregado al requerimiento de {$ordenOperacion->codigo_orden}."
            );
    }

    public function update(
        Request $request,
        MaterialRequeridoOrden $materialRequerido,
        MaterialRequeridoOrdenService $materiales
    ): RedirectResponse {
        $datos = $request->validate([
            'cantidad_nueva' => ['required', 'numeric', 'gt:0', 'max:999999999.999'],
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $cantidadEntregada = (float) DB::table('nota_salida_detalles as d')
            ->join('notas_salida as n', 'n.id', '=', 'd.nota_salida_id')
            ->where('n.orden_operacion_id', $materialRequerido->orden_operacion_id)
            ->where('n.estado', 'CONFIRMADA')
            ->where('d.producto_id', $materialRequerido->producto_id)
            ->where('d.tratamiento', 'CONSUMO')
            ->sum('d.cantidad');

        $materiales->modificar(
            $materialRequerido,
            (float) $datos['cantidad_nueva'],
            $cantidadEntregada,
            $datos['motivo'],
            $request->user()
        );

        return redirect()
            ->to(route('ordenes-operacion.show', $materialRequerido->orden_operacion_id).'#materiales-requeridos')
            ->with('success', 'Requerimiento actualizado. El cambio quedó registrado en el historial.');
    }

    private function cantidadVisible(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 2, '.', ''), '0'), '.');
    }
}
