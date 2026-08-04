<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Marca;
use App\Models\OrdenCompra;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Repisa;
use App\Models\Requisicion;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoBusquedaController extends Controller
{
    public function proveedores(Request $request): JsonResponse
    {
        [$termino, $incluirInactivos] = $this->parametros($request);

        $query = Proveedor::query();

        if (! $incluirInactivos) {
            $query->where('estado', true);
        }

        if ($termino !== '') {
            $query->where(function (Builder $busqueda) use ($termino): void {
                $busqueda
                    ->where('ruc', 'like', "%{$termino}%")
                    ->orWhere('razon_social', 'like', "%{$termino}%")
                    ->orWhere('nombre_comercial', 'like', "%{$termino}%");
            });
        }

        $items = $query
            ->orderBy('razon_social')
            ->limit(15)
            ->get()
            ->map(fn(Proveedor $proveedor): array => [
                'id' => $proveedor->id,
                'label' => $proveedor->ruc . ' — ' . $proveedor->nombreVisible(),
                'description' => $proveedor->razon_social,
            ]);

        return response()->json(['items' => $items]);
    }

    public function productos(Request $request): JsonResponse
    {
        [$termino, $incluirInactivos] = $this->parametros($request);

        $query = Producto::query()->with(['unidadMedida', 'inventarios']);

        if (! $incluirInactivos) {
            $query->where('estado', true);
        }

        if ($termino !== '') {
            $query->where(function (Builder $busqueda) use ($termino): void {
                $busqueda
                    ->where('codigo', 'like', "%{$termino}%")
                    ->orWhere('descripcion', 'like', "%{$termino}%");
            });
        }

        $items = $query
            ->orderBy('codigo')
            ->limit(15)
            ->get()
            ->map(function (Producto $producto): array {
                $unidad = $producto->unidadMedida?->abreviatura
                    ?? $producto->unidadMedida?->codigo
                    ?? $producto->unidadMedida?->nombre;

                return [
                    'id' => $producto->id,
                    'codigo' => $producto->codigo,
                    'descripcion' => $producto->descripcion,
                    'unidad' => $unidad,
                    'stock' => $producto->stockActualTotal(),
                    'costo_referencia' => $producto->costoPromedioActual(),
                    'label' => $producto->codigo . ' — ' . $producto->descripcion,
                    'description' => ($unidad ?: 'Sin unidad')
                        . ' · Stock ' . number_format($producto->stockActualTotal(), 3),
                ];
            });

        return response()->json(['items' => $items]);
    }

    public function clientes(Request $request): JsonResponse
    {
        [$termino] = $this->parametros($request);

        $query = Cliente::query()
            ->with('tipoCliente')
            ->where('estado', true);

        if ($termino !== '') {
            $query->where(function (Builder $busqueda) use ($termino): void {
                $busqueda
                    ->where('numero_documento', 'like', "%{$termino}%")
                    ->orWhere('ruc', 'like', "%{$termino}%")
                    ->orWhere('razon_social', 'like', "%{$termino}%")
                    ->orWhere('nombre_comercial', 'like', "%{$termino}%")
                    ->orWhere('nombres', 'like', "%{$termino}%")
                    ->orWhere('apellido_paterno', 'like', "%{$termino}%")
                    ->orWhere('apellido_materno', 'like', "%{$termino}%");
            });
        }

        $items = $query
            ->orderBy('razon_social')
            ->orderBy('nombres')
            ->limit(15)
            ->get()
            ->map(fn(Cliente $cliente): array => [
                'id' => $cliente->id,
                'margen_porcentaje' => (float) ($cliente->tipoCliente?->porcentaje_ganancia ?? 0),
                'tipo_cliente' => $cliente->tipoCliente?->nombre,
                'label' => $cliente->documentoVisible() . ' — ' . $cliente->nombreVisible(),
                'description' => collect([
                    $cliente->tipoCliente?->nombre,
                    $cliente->contacto ?: $cliente->telefono,
                ])->filter()->implode(' · '),
            ]);

        return response()->json(['items' => $items]);
    }

    public function requisiciones(Request $request): JsonResponse
    {
        [$termino] = $this->parametros($request);

        $query = Requisicion::query()->where('estado', '!=', 'ANULADA');

        if ($termino !== '') {
            $query->where(function (Builder $busqueda) use ($termino): void {
                $busqueda
                    ->where('codigo', 'like', "%{$termino}%")
                    ->orWhere('descripcion', 'like', "%{$termino}%");
            });
        }

        $items = $query
            ->latest('fecha_solicitud')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn(Requisicion $requisicion): array => [
                'id' => $requisicion->id,
                'label' => $requisicion->codigo . ' — '
                    . $requisicion->fecha_solicitud?->format('d/m/Y'),
                'description' => $requisicion->estado,
            ]);

        return response()->json(['items' => $items]);
    }

    public function ordenesCompra(Request $request): JsonResponse
    {
        [$termino] = $this->parametros($request);

        $query = OrdenCompra::query()
            ->with('proveedor')
            ->whereIn('estado', ['APROBADA', 'PARCIALMENTE_RECIBIDA'])
            ->whereHas('detalles', function (Builder $detalle): void {
                $detalle->whereColumn('cantidad_recibida', '<', 'cantidad_ordenada');
            });

        if ($termino !== '') {
            $query->where(function (Builder $busqueda) use ($termino): void {
                $busqueda
                    ->where('codigo', 'like', "%{$termino}%")
                    ->orWhere('numero_documento_proveedor', 'like', "%{$termino}%")
                    ->orWhereHas('proveedor', function (Builder $proveedor) use ($termino): void {
                        $proveedor
                            ->where('ruc', 'like', "%{$termino}%")
                            ->orWhere('razon_social', 'like', "%{$termino}%")
                            ->orWhere('nombre_comercial', 'like', "%{$termino}%");
                    });
            });
        }

        $items = $query
            ->latest('fecha_emision')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn(OrdenCompra $orden): array => [
                'id' => $orden->id,
                'label' => $orden->codigo . ' — '
                    . ($orden->proveedor?->nombreVisible() ?? 'Sin proveedor'),
                'description' => $orden->estado . ' · ' . $orden->fecha_emision?->format('d/m/Y'),
            ]);

        return response()->json(['items' => $items]);
    }

    public function ordenesOperacion(Request $request): JsonResponse
    {
        [$termino] = $this->parametros($request);

        $query = OrdenOperacion::query()
            ->with(['tipoOrden', 'cliente'])
            ->whereNotIn('estado', ['ANULADA', 'CERRADA']);

        if ($termino !== '') {
            $query->where(function (Builder $busqueda) use ($termino): void {
                $busqueda
                    ->where('codigo_orden', 'like', "%{$termino}%")
                    ->orWhere('descripcion', 'like', "%{$termino}%")
                    ->orWhereHas('cliente', function (Builder $cliente) use ($termino): void {
                        $cliente
                            ->where('numero_documento', 'like', "%{$termino}%")
                            ->orWhere('ruc', 'like', "%{$termino}%")
                            ->orWhere('razon_social', 'like', "%{$termino}%")
                            ->orWhere('nombre_comercial', 'like', "%{$termino}%");
                    });
            });
        }

        $items = $query
            ->latest('fecha_apertura')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn(OrdenOperacion $orden): array => [
                'id' => $orden->id,
                'label' => $orden->codigo_orden . ' — '
                    . ($orden->cliente?->nombreVisible() ?? 'Sin cliente'),
                'description' => ($orden->tipoOrden?->codigo ?? 'Sin tipo') . ' · ' . $orden->estado,
            ]);

        return response()->json(['items' => $items]);
    }

    public function repisas(Request $request): JsonResponse
    {
        [$termino, $incluirInactivos] = $this->parametros($request);

        $query = Repisa::query();

        if (! $incluirInactivos) {
            $query->where('estado', true);
        }

        if ($termino !== '') {
            $query->where(function (Builder $busqueda) use ($termino): void {
                $busqueda
                    ->where('codigo', 'like', "%{$termino}%")
                    ->orWhere('descripcion', 'like', "%{$termino}%");
            });
        }

        $items = $query
            ->orderBy('codigo')
            ->limit(15)
            ->get()
            ->map(fn(Repisa $repisa): array => [
                'id' => $repisa->id,
                'label' => $repisa->codigo
                    . ($repisa->descripcion ? ' — ' . $repisa->descripcion : ''),
                'description' => $repisa->estado ? 'Activa' : 'Inactiva',
            ]);

        return response()->json(['items' => $items]);
    }

    public function marcas(Request $request): JsonResponse
    {
        [$termino, $incluirInactivos] = $this->parametros($request);

        $query = Marca::query();

        if (! $incluirInactivos) {
            $query->where('estado', true);
        }

        if ($termino !== '') {
            $query->where('nombre', 'like', "%{$termino}%");
        }

        $items = $query
            ->orderBy('nombre')
            ->limit(15)
            ->get()
            ->map(fn(Marca $marca): array => [
                'id' => $marca->id,
                'label' => $marca->nombre,
                'description' => $marca->estado ? 'Activa' : 'Inactiva',
            ]);

        return response()->json(['items' => $items]);
    }

    public function relacionesCliente(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
        ]);
        $clienteId = ! empty($data['cliente_id']) ? (int) $data['cliente_id'] : null;

        $direcciones = $clienteId
            ? Cliente::query()->findOrFail($clienteId)->direcciones()
            ->where('estado', true)
            ->orderByDesc('es_principal')
            ->orderBy('destino')
            ->get()
            ->map(fn($direccion): array => [
                'id' => $direccion->id,
                'label' => $direccion->destino
                    ?: $direccion->direccion
                    ?: 'Dirección ' . $direccion->id,
            ])
            : collect();

        $vehiculos = Vehiculo::query()
            ->where('estado', true)
            ->where(function (Builder $query) use ($clienteId): void {
                if ($clienteId) {
                    $query->where('cliente_id', $clienteId)
                        ->orWhereNull('cliente_id');
                } else {
                    $query->whereNull('cliente_id');
                }
            })
            ->orderBy('placa')
            ->get()
            ->map(fn($vehiculo): array => [
                'id' => $vehiculo->id,
                'label' => $vehiculo->identificadorVisible() . ' — '
                    . $vehiculo->descripcionVisible(),
            ]);

        return response()->json([
            'direcciones' => $direcciones,
            'vehiculos' => $vehiculos,
        ]);
    }

    private function parametros(Request $request): array
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'todos' => ['nullable', 'boolean'],
        ]);

        return [
            trim((string) ($data['q'] ?? '')),
            $request->boolean('todos'),
        ];
    }
}
