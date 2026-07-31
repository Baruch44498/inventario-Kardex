<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehiculoRequest;
use App\Http\Requests\UpdateVehiculoRequest;
use App\Models\Cliente;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VehiculoController extends Controller
{
    public function create(Cliente $cliente): View
    {
        return view('vehiculos.create', compact('cliente'));
    }

    public function store(
        StoreVehiculoRequest $request,
        Cliente $cliente
    ): RedirectResponse {
        $vehiculo = $cliente->vehiculos()->create([
            ...$request->validated(),
            'es_comodin' => false,
        ]);

        return redirect()
            ->route(
                'clientes.vehiculos.show',
                [$cliente->id, $vehiculo->id]
            )
            ->with(
                'success',
                'Vehículo registrado correctamente.'
            );
    }

    public function show(
        Cliente $cliente,
        Vehiculo $vehiculo
    ): View {
        $this->asegurarPertenencia($cliente, $vehiculo);

        $baseQuery = $vehiculo
            ->ordenesOperacion()
            ->with([
                'tipoOrden',
                'creador',
            ])
            ->withCount([
                'notasSalida',
                'requisiciones',
            ]);

        $resumen = [
            'total' => (clone $baseQuery)->count(),
            'abiertas' => (clone $baseQuery)
                ->where('estado', 'ABIERTA')
                ->count(),
            'en_proceso' => (clone $baseQuery)
                ->where('estado', 'EN_PROCESO')
                ->count(),
            'cerradas' => (clone $baseQuery)
                ->where('estado', 'CERRADA')
                ->count(),
        ];

        $ordenes = $baseQuery
            ->orderByDesc('fecha_apertura')
            ->orderByDesc('id')
            ->paginate(12);

        return view('vehiculos.show', compact(
            'cliente',
            'vehiculo',
            'ordenes',
            'resumen'
        ));
    }

    public function edit(
        Cliente $cliente,
        Vehiculo $vehiculo
    ): View {
        $this->asegurarPertenencia($cliente, $vehiculo);
        $this->protegerComodin($vehiculo);

        return view(
            'vehiculos.edit',
            compact('cliente', 'vehiculo')
        );
    }

    public function update(
        UpdateVehiculoRequest $request,
        Cliente $cliente,
        Vehiculo $vehiculo
    ): RedirectResponse {
        $this->asegurarPertenencia($cliente, $vehiculo);
        $this->protegerComodin($vehiculo);

        $vehiculo->update($request->validated());

        return redirect()
            ->route(
                'clientes.vehiculos.show',
                [$cliente->id, $vehiculo->id]
            )
            ->with(
                'success',
                'Vehículo actualizado correctamente.'
            );
    }

    public function toggle(
        Cliente $cliente,
        Vehiculo $vehiculo
    ): RedirectResponse {
        $this->asegurarPertenencia($cliente, $vehiculo);
        $this->protegerComodin($vehiculo);

        if (
            $vehiculo->estado
            && $vehiculo->ordenesOperacion()
            ->whereIn(
                'estado',
                ['ABIERTA', 'EN_PROCESO']
            )
            ->exists()
        ) {
            return back()->with(
                'error',
                'No se puede desactivar un vehículo asociado a órdenes activas.'
            );
        }

        $vehiculo->update([
            'estado' => ! $vehiculo->estado,
        ]);

        return back()->with(
            'success',
            $vehiculo->estado
                ? 'Vehículo activado.'
                : 'Vehículo desactivado.'
        );
    }

    private function asegurarPertenencia(
        Cliente $cliente,
        Vehiculo $vehiculo
    ): void {
        abort_unless(
            $vehiculo->cliente_id === $cliente->id,
            404
        );
    }

    private function protegerComodin(
        Vehiculo $vehiculo
    ): void {
        abort_if(
            $vehiculo->es_comodin,
            403,
            'El vehículo comodín es un registro protegido del sistema.'
        );
    }
}
