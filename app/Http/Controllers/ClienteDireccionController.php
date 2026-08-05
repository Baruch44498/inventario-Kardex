<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClienteDireccionRequest;
use App\Http\Requests\UpdateClienteDireccionRequest;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            $clienteBloqueado = Cliente::query()
                ->lockForUpdate()
                ->findOrFail($cliente->id);
            $datos = $request->validated();
            $tienePrincipalActiva = $clienteBloqueado->direcciones()
                ->where('estado', true)
                ->where('es_principal', true)
                ->exists();

            if (! $tienePrincipalActiva) {
                $datos['es_principal'] = true;
                $datos['estado'] = true;
            }

            if ($datos['es_fiscal']) {
                $datos['estado'] = true;
                $clienteBloqueado->direcciones()->update(['es_fiscal' => false]);
            }

            if ($datos['es_principal']) {
                $clienteBloqueado->direcciones()->update(['es_principal' => false]);
            }

            $clienteBloqueado->direcciones()->create($datos);
        });

        return redirect()
            ->route('clientes.show', $cliente->id)
            ->with('success', 'Dirección registrada correctamente.');
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
            $clienteBloqueado = Cliente::query()
                ->lockForUpdate()
                ->findOrFail($cliente->id);
            $direccionBloqueada = $clienteBloqueado->direcciones()
                ->whereKey($direccion->id)
                ->lockForUpdate()
                ->firstOrFail();
            $datos = $request->validated();

            if (
                $clienteBloqueado->requiereDireccionFiscal()
                && $direccionBloqueada->es_fiscal
                && $direccionBloqueada->estado
                && (! $datos['es_fiscal'] || ! $datos['estado'])
            ) {
                throw ValidationException::withMessages([
                    'es_fiscal' => 'Primero asigna otra dirección fiscal activa a la empresa.',
                ]);
            }

            if ($datos['es_fiscal']) {
                $clienteBloqueado->direcciones()
                    ->where('id', '<>', $direccionBloqueada->id)
                    ->update(['es_fiscal' => false]);
                $datos['estado'] = true;
            }

            if ($datos['es_principal']) {
                $clienteBloqueado->direcciones()
                    ->where('id', '<>', $direccionBloqueada->id)
                    ->update(['es_principal' => false]);
                $datos['estado'] = true;
            }

            $direccionBloqueada->update($datos);

            if (! $direccionBloqueada->estado || ! $direccionBloqueada->es_principal) {
                $this->garantizarPrincipal($clienteBloqueado, $direccionBloqueada->id);
            }
        });

        return redirect()
            ->route('clientes.show', $cliente->id)
            ->with('success', 'Dirección actualizada correctamente.');
    }

    public function toggle(
        Cliente $cliente,
        ClienteDireccion $direccion
    ): RedirectResponse {
        $this->asegurarPertenencia($cliente, $direccion);

        DB::transaction(function () use ($cliente, $direccion): void {
            $clienteBloqueado = Cliente::query()
                ->lockForUpdate()
                ->findOrFail($cliente->id);
            $direccionBloqueada = $clienteBloqueado->direcciones()
                ->whereKey($direccion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $clienteBloqueado->requiereDireccionFiscal()
                && $direccionBloqueada->es_fiscal
                && $direccionBloqueada->estado
            ) {
                throw ValidationException::withMessages([
                    'direccion' => 'La empresa no puede quedar sin una dirección fiscal activa. Asigna otra antes de desactivarla.',
                ]);
            }

            $direccionBloqueada->update(['estado' => ! $direccionBloqueada->estado]);

            if (! $direccionBloqueada->estado && $direccionBloqueada->es_principal) {
                $direccionBloqueada->update(['es_principal' => false]);
            }

            $this->garantizarPrincipal($clienteBloqueado, $direccionBloqueada->id);
        });

        $direccion->refresh();

        return back()->with(
            'success',
            $direccion->estado
                ? 'Dirección activada.'
                : 'Dirección desactivada.'
        );
    }

    public function principal(
        Cliente $cliente,
        ClienteDireccion $direccion
    ): RedirectResponse {
        $this->asegurarPertenencia($cliente, $direccion);

        DB::transaction(function () use ($cliente, $direccion): void {
            $clienteBloqueado = Cliente::query()
                ->lockForUpdate()
                ->findOrFail($cliente->id);
            $direccionBloqueada = $clienteBloqueado->direcciones()
                ->whereKey($direccion->id)
                ->lockForUpdate()
                ->firstOrFail();

            $clienteBloqueado->direcciones()->update(['es_principal' => false]);
            $direccionBloqueada->update([
                'es_principal' => true,
                'estado' => true,
            ]);
        });

        return back()->with('success', 'Dirección principal actualizada.');
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
