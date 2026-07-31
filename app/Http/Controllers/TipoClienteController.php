<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTipoClienteRequest;
use App\Models\TipoCliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TipoClienteController extends Controller
{
    public function index(): View
    {
        $tipos = TipoCliente::query()
            ->withCount([
                'clientes',
                'clientes as clientes_activos_count' => fn($query) => $query->where('estado', true),
            ])
            ->orderBy('id')
            ->get();

        return view('tipos_cliente.index', compact('tipos'));
    }

    public function update(
        UpdateTipoClienteRequest $request,
        TipoCliente $tipoCliente
    ): RedirectResponse {
        $datos = $request->validated();

        if (
            ! $datos['estado']
            && $tipoCliente->clientes()->where('estado', true)->exists()
        ) {
            return back()->with(
                'error',
                'No se puede desactivar un tipo utilizado por clientes activos.'
            );
        }

        $tipoCliente->update($datos);

        return back()->with(
            'success',
            "Tipo {$tipoCliente->nombre} actualizado."
        );
    }
}
