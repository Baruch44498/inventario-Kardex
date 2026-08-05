<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use App\Models\TipoCliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'tipo_cliente_id' => [
                'nullable',
                'integer',
                'exists:tipos_cliente,id',
            ],
            'estado' => ['nullable', 'in:1,0'],
        ]);

        $query = Cliente::query()
            ->with('tipoCliente')
            ->withCount([
                'direcciones',
                'vehiculos',
                'ordenesOperacion',
            ]);

        if (! empty($filtros['q'])) {
            $busqueda = trim($filtros['q']);

            $query->where(
                function ($subquery) use ($busqueda): void {
                    $subquery
                        ->where(
                            'razon_social',
                            'like',
                            "%{$busqueda}%"
                        )
                        ->orWhere(
                            'nombre_comercial',
                            'like',
                            "%{$busqueda}%"
                        )
                        ->orWhere(
                            'numero_documento',
                            'like',
                            "%{$busqueda}%"
                        )
                        ->orWhere('ruc', 'like', "%{$busqueda}%")
                        ->orWhere(
                            'nombres',
                            'like',
                            "%{$busqueda}%"
                        )
                        ->orWhere(
                            'apellido_paterno',
                            'like',
                            "%{$busqueda}%"
                        )
                        ->orWhere(
                            'apellido_materno',
                            'like',
                            "%{$busqueda}%"
                        )
                        ->orWhere(
                            'contacto',
                            'like',
                            "%{$busqueda}%"
                        );
                }
            );
        }

        if (! empty($filtros['tipo_cliente_id'])) {
            $query->where(
                'tipo_cliente_id',
                $filtros['tipo_cliente_id']
            );
        }

        if (
            array_key_exists('estado', $filtros)
            && $filtros['estado'] !== null
            && $filtros['estado'] !== ''
        ) {
            $query->where(
                'estado',
                (bool) $filtros['estado']
            );
        }

        $clientes = $query
            ->orderByDesc('estado')
            ->orderByDesc('es_mostrador')
            ->orderBy('razon_social')
            ->paginate(15)
            ->withQueryString();

        $tipos = TipoCliente::query()
            ->activos()
            ->withCount('clientes')
            ->orderBy('nombre')
            ->get();

        $resumen = [
            'total' => Cliente::query()->count(),
            'activos' => Cliente::query()
                ->where('estado', true)
                ->count(),
            'con_vehiculos' => Cliente::query()
                ->has('vehiculos')
                ->count(),
            'con_ordenes' => Cliente::query()
                ->has('ordenesOperacion')
                ->count(),
        ];

        return view('clientes.index', compact(
            'clientes',
            'tipos',
            'resumen'
        ));
    }

    public function create(): View
    {
        return view('clientes.create', [
            'tipos' => $this->tiposActivos(),
        ]);
    }

    public function store(
        StoreClienteRequest $request
    ): RedirectResponse {
        $cliente = Cliente::query()->create([
            ...$request->validated(),
            'es_mostrador' => false,
        ]);

        return redirect()
            ->route('clientes.show', $cliente->id)
            ->with(
                'success',
                'Cliente registrado correctamente.'
            );
    }

    public function show(Cliente $cliente): View
    {
        $cliente->load([
            'tipoCliente',
            'direcciones' => fn($query) => $query
                ->orderByDesc('es_fiscal')
                ->orderByDesc('es_principal')
                ->orderByDesc('estado')
                ->orderBy('destino'),
            'vehiculos' => fn($query) => $query
                ->withCount('ordenesOperacion')
                ->orderByDesc('es_comodin')
                ->orderByDesc('estado')
                ->orderBy('placa'),
            'ordenesOperacion.tipoOrden',
        ]);

        return view('clientes.show', compact('cliente'));
    }

    public function edit(Cliente $cliente): View
    {
        return view('clientes.edit', [
            'cliente' => $cliente,
            'tipos' => $this->tiposActivos(),
        ]);
    }

    public function update(
        UpdateClienteRequest $request,
        Cliente $cliente
    ): RedirectResponse {
        $datos = $request->validated();

        if ($cliente->es_mostrador) {
            $datos = [
                ...$datos,
                'tipo_cliente_id' => $cliente->tipo_cliente_id,
                'tipo_documento' => 'SIN_DOCUMENTO',
                'numero_documento' => null,
                'ruc' => null,
                'razon_social' => 'PÚBLICO GENERAL',
                'nombres' => 'PÚBLICO GENERAL',
                'apellido_paterno' => null,
                'apellido_materno' => null,
                'nombre_comercial' => 'Venta directa',
                'es_mostrador' => true,
                'estado' => true,
            ];
        }

        $cliente->update($datos);

        return redirect()
            ->route('clientes.show', $cliente->id)
            ->with(
                'success',
                'Cliente actualizado correctamente.'
            );
    }

    public function toggle(
        Cliente $cliente
    ): RedirectResponse {
        if ($cliente->es_mostrador) {
            return back()->with(
                'error',
                'PÚBLICO GENERAL es un registro del sistema y debe permanecer activo.'
            );
        }

        if (
            $cliente->estado
            && $cliente->ordenesOperacion()
            ->whereIn(
                'estado',
                ['ABIERTA', 'EN_PROCESO']
            )
            ->exists()
        ) {
            return back()->with(
                'error',
                'No se puede desactivar un cliente con órdenes abiertas o en proceso.'
            );
        }

        $cliente->update([
            'estado' => ! $cliente->estado,
        ]);

        return back()->with(
            'success',
            $cliente->estado
                ? 'Cliente activado correctamente.'
                : 'Cliente desactivado correctamente.'
        );
    }

    private function tiposActivos()
    {
        return TipoCliente::query()
            ->activos()
            ->orderBy('nombre')
            ->get();
    }
}
