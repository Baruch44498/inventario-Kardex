<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\Marca;
use App\Models\OrdenCompra;
use App\Models\OrdenOperacion;
use App\Models\NotaSalida;
use App\Models\Proforma;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Repisa;
use App\Models\Requisicion;
use App\Models\Vehiculo;
use App\Services\Inventario\DisponibilidadMaterialService;
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

    public function productos(
        Request $request,
        DisponibilidadMaterialService $disponibilidad
    ): JsonResponse {
        [$termino, $incluirInactivos] = $this->parametros($request);
        $contexto = (string) $request->query('contexto');
        $esProformaAlmacen = $contexto === 'proforma_almacen';
        $esReservaOrden = $contexto === 'reserva_orden';
        $esRequerimientoCompra = $contexto === 'requerimiento_compra';

        $query = Producto::query()->with(['unidadMedida', 'inventarios']);

        if (! $incluirInactivos) {
            $query->where('estado', true);
        }

        if ($esProformaAlmacen) {
            $query->whereHas(
                'inventarios',
                fn($inventario) => $inventario->where('stock_actual', '>', 0)
            );
        }

        if ($termino !== '') {
            $query->where(function (Builder $busqueda) use ($termino): void {
                $busqueda
                    ->where('codigo', 'like', "%{$termino}%")
                    ->orWhere('descripcion', 'like', "%{$termino}%");
            });
        }

        $productos = $query
            ->orderBy('codigo')
            ->limit(15)
            ->get();

        $resumenes = ($esReservaOrden || $esRequerimientoCompra)
            ? $disponibilidad->resumenesProductos(
                $productos->pluck('id'),
                $request->integer('orden_id') ?: null
            )
            : collect();

        $puedeVerCostoReferencia = $request->user()?->puede('inventario.ver')
            || $request->user()?->puede('compras.gestionar')
            || $request->user()?->puede('proformas.cotizar');

        $items = $productos
            ->map(function (Producto $producto) use (
                $esProformaAlmacen,
                $esReservaOrden,
                $esRequerimientoCompra,
                $resumenes,
                $puedeVerCostoReferencia
            ): array {
                $unidad = $producto->unidadMedida?->abreviatura
                    ?? $producto->unidadMedida?->codigo
                    ?? $producto->unidadMedida?->nombre;

                $item = [
                    'id' => $producto->id,
                    'codigo' => $producto->codigo,
                    'descripcion' => $producto->descripcion,
                    'unidad' => $unidad,
                    'stock' => $producto->stockActualTotal(),
                    'label' => $producto->codigo . ' — ' . $producto->descripcion,
                    'description' => ($unidad ?: 'Sin unidad')
                        . ($esProformaAlmacen ? ' · Disponible físico ' : ' · Stock ')
                        . number_format($producto->stockActualTotal(), 2),
                ];

                if ($esReservaOrden || $esRequerimientoCompra) {
                    $resumen = $resumenes->get($producto->id, []);
                    $item['stock_fisico'] = (float) ($resumen['stock_fisico'] ?? 0);
                    $item['reservado'] = (float) ($resumen['reservado'] ?? 0);
                    $item['disponible'] = (float) ($resumen['disponible'] ?? 0);
                    $item['stock_minimo'] = (float) ($resumen['stock_minimo'] ?? 0);
                    $item['cantidad_sugerida'] = (float) ($resumen['necesidad_abastecimiento'] ?? 0);
                    $item['description'] = ($unidad ?: 'Sin unidad')
                        . ' · Físico ' . number_format((float) ($resumen['stock_fisico'] ?? 0), 2)
                        . ' · Reservado ' . number_format((float) ($resumen['reservado'] ?? 0), 2)
                        . ' · Disponible ' . number_format((float) ($resumen['disponible'] ?? 0), 2)
                        . ($esRequerimientoCompra
                            ? ' · Comprar sugerido ' . number_format((float) ($resumen['necesidad_abastecimiento'] ?? 0), 2)
                            : '');
                } elseif (! $esProformaAlmacen && $puedeVerCostoReferencia) {
                    $item['costo_referencia'] = $producto->costoPromedioActual();
                }

                return $item;
            });

        return response()->json(['items' => $items]);
    }

    public function clientes(Request $request): JsonResponse
    {
        [$termino] = $this->parametros($request);
        $esProformaAlmacen = $request->query('contexto') === 'proforma_almacen';

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
            ->map(function (Cliente $cliente) use ($esProformaAlmacen): array {
                $item = [
                    'id' => $cliente->id,
                    'tipo_cliente' => $cliente->tipoCliente?->nombre,
                    'label' => $cliente->documentoVisible() . ' — ' . $cliente->nombreVisible(),
                    'description' => collect([
                        $cliente->tipoCliente?->nombre,
                        $cliente->contacto ?: $cliente->telefono,
                    ])->filter()->implode(' · '),
                ];

                if (! $esProformaAlmacen) {
                    $item['margen_porcentaje'] = (float) (
                        $cliente->tipoCliente?->porcentaje_ganancia ?? 0
                    );
                }

                return $item;
            });

        return response()->json(['items' => $items]);
    }

    public function requisiciones(Request $request): JsonResponse
    {
        [$termino] = $this->parametros($request);

        $query = Requisicion::query()
            ->whereIn('estado', ['ENVIADA', 'EN_REVISION', 'COTIZANDO', 'ATENDIDA']);

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
                'description' => 'Requerimiento · ' . $requisicion->estado,
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
            ->where('estado', 'EN_PROCESO');

        if ($termino !== '') {
            $query->where(function (Builder $busqueda) use ($termino): void {
                $busqueda
                    ->where('codigo_orden', 'like', "%{$termino}%")
                    ->orWhere('descripcion', 'like', "%{$termino}%")
                    ->orWhereHas('tipoOrden', function (Builder $tipoOrden) use ($termino): void {
                        $tipoOrden
                            ->where('codigo', 'like', "%{$termino}%")
                            ->orWhere('nombre', 'like', "%{$termino}%");
                    })
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
                'description' => ($orden->tipoOrden?->codigo ?? 'Sin tipo')
                    . ' · ' . ($orden->tipoOrden?->nombre ?? 'Orden')
                    . ' · EN_PROCESO · '
                    . ($orden->descripcion ?: 'Sin descripción'),
            ]);

        return response()->json(['items' => $items]);
    }

    public function existenciasSalida(
        Request $request,
        DisponibilidadMaterialService $disponibilidad
    ): JsonResponse {
        [$termino] = $this->parametros($request);
        $ordenId = $request->integer('orden_id');

        if ($termino === '' || $ordenId <= 0) {
            return response()->json(['items' => []]);
        }

        $orden = OrdenOperacion::query()
            ->where('estado', 'EN_PROCESO')
            ->find($ordenId);

        if (! $orden) {
            return response()->json(['items' => []]);
        }

        $planificados = $orden->materialesRequeridos()
            ->whereNotNull('producto_id')
            ->pluck('producto_id')
            ->unique()
            ->values();

        $query = Inventario::query()
            ->with(['producto.unidadMedida', 'repisa'])
            ->where('stock_actual', '>', 0)
            ->whereHas('producto', fn(Builder $producto) => $producto->where('estado', true))
            ->whereHas('repisa', fn(Builder $repisa) => $repisa->where('estado', true))
            ->where(function (Builder $busqueda) use ($termino): void {
                $busqueda
                    ->whereHas('producto', function (Builder $producto) use ($termino): void {
                        $producto
                            ->where('codigo', 'like', "%{$termino}%")
                            ->orWhere('descripcion', 'like', "%{$termino}%");
                    })
                    ->orWhereHas(
                        'repisa',
                        fn(Builder $repisa) =>
                        $repisa->where('codigo', 'like', "%{$termino}%")
                    );
            });

        if ($planificados->isNotEmpty()) {
            $query->whereNotIn('producto_id', $planificados);
        }

        $inventarios = $query
            ->join('productos as p', 'p.id', '=', 'inventarios.producto_id')
            ->join('repisas as r', 'r.id', '=', 'inventarios.repisa_id')
            ->select('inventarios.*')
            ->orderBy('p.codigo')
            ->orderBy('r.codigo')
            ->limit(15)
            ->get();

        $resumenes = $disponibilidad->resumenesProductos(
            $inventarios->pluck('producto_id'),
            $orden->id
        );

        $items = $inventarios->map(function (Inventario $inventario) use ($resumenes): array {
            $resumen = $resumenes->get($inventario->producto_id, []);
            $unidad = $inventario->producto?->unidadMedida?->abreviatura
                ?? $inventario->producto?->unidadMedida?->codigo
                ?? $inventario->producto?->unidadMedida?->nombre;

            return [
                'id' => $inventario->id,
                'inventario_id' => $inventario->id,
                'producto_id' => $inventario->producto_id,
                'repisa_id' => $inventario->repisa_id,
                'codigo' => $inventario->producto?->codigo,
                'descripcion' => $inventario->producto?->descripcion,
                'unidad' => $unidad,
                'permite_fraccionamiento' => (bool) $inventario->producto?->permite_fraccionamiento,
                'repisa' => $inventario->repisa?->codigo,
                'stock_actual' => round((float) $inventario->stock_actual, 3),
                'stock_total_producto' => round((float) ($resumen['stock_fisico'] ?? $inventario->stock_actual), 3),
                'reserva_orden' => round((float) ($resumen['reservado_orden'] ?? 0), 3),
                'reservado_global' => round((float) ($resumen['reservado'] ?? 0), 3),
                'disponible_libre' => round((float) ($resumen['disponible'] ?? $inventario->stock_actual), 3),
                'herramientas_en_uso' => round((float) ($resumen['herramientas_en_uso'] ?? 0), 3),
                'label' => ($inventario->producto?->codigo ?? 'Producto') . ' — '
                    . ($inventario->producto?->descripcion ?? 'Sin descripción'),
                'description' => 'Repisa ' . ($inventario->repisa?->codigo ?? '—')
                    . ' · Stock ' . number_format((float) $inventario->stock_actual, 2)
                    . ' · Disponible ' . number_format((float) ($resumen['disponible'] ?? 0), 2),
            ];
        });

        return response()->json(['items' => $items]);
    }


    public function proformasAlmacen(Request $request): JsonResponse
    {
        [$termino] = $this->parametros($request);
        $contexto = (string) $request->query('contexto', 'salida');

        $query = Proforma::query()
            ->with('cliente')
            ->where('estado', '!=', 'ANULADA')
            ->whereHas('detalles');

        if ($contexto === 'reposicion_prestamo') {
            $query->whereHas(
                'detalles',
                fn(Builder $detalle) =>
                $detalle->where('tratamiento', 'PRESTAMO')
            );
        }

        if ($termino !== '') {
            $query->where(function (Builder $busqueda) use ($termino): void {
                $busqueda
                    ->where('codigo', 'like', "%{$termino}%")
                    ->orWhereHas('cliente', function (Builder $cliente) use ($termino): void {
                        $cliente
                            ->where('razon_social', 'like', "%{$termino}%")
                            ->orWhere('ruc', 'like', "%{$termino}%")
                            ->orWhere('numero_documento', 'like', "%{$termino}%");
                    });
            });
        }

        $items = $query
            ->latest('fecha_emision')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn(Proforma $proforma): array => [
                'id' => $proforma->id,
                'label' => $proforma->codigo . ' — '
                    . ($proforma->cliente?->nombreVisible() ?? 'Sin cliente'),
                'description' => $proforma->estado . ' · '
                    . $proforma->fecha_emision?->format('d/m/Y'),
            ]);

        return response()->json(['items' => $items]);
    }

    public function notasSalida(Request $request): JsonResponse
    {
        [$termino] = $this->parametros($request);
        $contexto = (string) $request->query('contexto', 'retorno_material');
        $tratamiento = $contexto === 'devolucion_herramienta'
            ? 'USO_TEMPORAL'
            : 'CONSUMO';

        $query = NotaSalida::query()
            ->with(['ordenOperacion', 'proforma.cliente'])
            ->where('estado', 'CONFIRMADA')
            ->whereHas(
                'detalles',
                fn(Builder $detalle) =>
                $detalle->where('tratamiento', $tratamiento)
            );

        if ($termino !== '') {
            $query->where(function (Builder $busqueda) use ($termino): void {
                $busqueda
                    ->where('codigo', 'like', "%{$termino}%")
                    ->orWhere('entregado_a', 'like', "%{$termino}%")
                    ->orWhereHas(
                        'ordenOperacion',
                        fn(Builder $orden) =>
                        $orden->where('codigo_orden', 'like', "%{$termino}%")
                    )
                    ->orWhereHas(
                        'proforma',
                        fn(Builder $proforma) =>
                        $proforma->where('codigo', 'like', "%{$termino}%")
                    );
            });
        }

        $items = $query
            ->latest('fecha_salida')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn(NotaSalida $nota): array => [
                'id' => $nota->id,
                'label' => $nota->codigo . ' — ' . ($nota->entregado_a ?: 'Sin receptor'),
                'description' => $nota->motivoVisible() . ' · '
                    . $nota->fecha_salida?->format('d/m/Y'),
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
            ->orderByDesc('es_fiscal')
            ->orderByDesc('es_principal')
            ->orderBy('destino')
            ->get()
            ->map(fn($direccion): array => [
                'id' => $direccion->id,
                'label' => ($direccion->es_fiscal ? 'Dirección fiscal — ' : '')
                    . ($direccion->destino
                        ?: $direccion->direccion
                        ?: 'Dirección ' . $direccion->id),
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

        $cliente = $clienteId
            ? Cliente::query()->findOrFail($clienteId)
            : null;

        return response()->json([
            'direcciones' => $direcciones,
            'vehiculos' => $vehiculos,
            'requiere_direccion_fiscal' => $cliente?->requiereDireccionFiscal() ?? false,
            'tiene_direccion_fiscal' => $cliente?->tieneDireccionFiscalActiva() ?? false,
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
