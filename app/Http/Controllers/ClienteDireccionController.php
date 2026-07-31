<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClienteDireccionRequest;
use App\Http\Requests\UpdateClienteDireccionRequest;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClienteDireccionController extends Controller
{
    public function create(Cliente $cliente): View
    {
        return view('cliente_direcciones.create', compact('cliente'));
    }

    public function store(
        StoreClienteDireccionRequest $request,
        Cliente $cliente
    ): RedirectResponse {
        DB::transaction(function () use ($request, $cliente): void {
            $datos = $request->validated();
            $tienePrincipalActiva = $cliente->direcciones()
                ->where('estado', true)
                ->where('es_principal', true)
                ->exists();

            if (! $tienePrincipalActiva) {
                $datos['es_principal'] = true;
                $datos['estado'] = true;
            }

            if ($datos['es_principal']) {
                $cliente->direcciones()->update(['es_principal' => false]);
            }

            $cliente->direcciones()->create($datos);
        });

        return redirect()
            ->route('clientes.show', $cliente->id)
            ->with('success', 'Dirección fiscal registrada correctamente.');
    }

    public function edit(
        Cliente $cliente,
        ClienteDireccion $direccion
    ): View {
        $this->asegurarPertenencia($cliente, $direccion);

        return view('cliente_direcciones.edit', compact(
            'cliente',
            'direccion'
        ));
    }

    public function update(
        UpdateClienteDireccionRequest $request,
        Cliente $cliente,
        ClienteDireccion $direccion
    ): RedirectResponse {
        $this->asegurarPertenencia($cliente, $direccion);

        DB::transaction(function () use ($request, $cliente, $direccion): void {
            $datos = $request->validated();

            if ($datos['es_principal']) {
                $cliente->direcciones()
                    ->where('id', '<>', $direccion->id)
                    ->update(['es_principal' => false]);
                $datos['estado'] = true;
            }

            $direccion->update($datos);

            if (! $direccion->estado || ! $direccion->es_principal) {
                $this->garantizarPrincipal($cliente, $direccion->id);
            }
        });

        return redirect()
            ->route('clientes.show', $cliente->id)
            ->with('success', 'Dirección fiscal actualizada correctamente.');
    }

    public function toggle(
        Cliente $cliente,
        ClienteDireccion $direccion
    ): RedirectResponse {
        $this->asegurarPertenencia($cliente, $direccion);

        DB::transaction(function () use ($cliente, $direccion): void {
            $direccion->update(['estado' => ! $direccion->estado]);

            if (! $direccion->estado && $direccion->es_principal) {
                $direccion->update(['es_principal' => false]);
            }

            $this->garantizarPrincipal($cliente, $direccion->id);
        });

        return back()->with(
            'success',
            $direccion->estado
                ? 'Dirección fiscal activada.'
                : 'Dirección fiscal desactivada.'
        );
    }

    public function principal(
        Cliente $cliente,
        ClienteDireccion $direccion
    ): RedirectResponse {
        $this->asegurarPertenencia($cliente, $direccion);

        DB::transaction(function () use ($cliente, $direccion): void {
            $cliente->direcciones()->update(['es_principal' => false]);
            $direccion->update([
                'es_principal' => true,
                'estado' => true,
            ]);
        });

        return back()->with('success', 'Dirección fiscal principal actualizada.');
    }

    private function garantizarPrincipal(
        Cliente $cliente,
        int $excluirId
    ): void {
        if ($cliente->direcciones()
            ->where('estado', true)
            ->where('es_principal', true)
            ->exists()
        ) {
            return;
        }

        $alternativa = $cliente->direcciones()
            ->where('estado', true)
            ->where('id', '<>', $excluirId)
            ->oldest('id')
            ->first();

        if ($alternativa) {
            $alternativa->update(['es_principal' => true]);

            return;
        }

        $actual = $cliente->direcciones()
            ->whereKey($excluirId)
            ->where('estado', true)
            ->first();

        $actual?->update(['es_principal' => true]);
    }

    private function asegurarPertenencia(
        Cliente $cliente,
        ClienteDireccion $direccion
    ): void {
        abort_unless($direccion->cliente_id === $cliente->id, 404);
    }
}
