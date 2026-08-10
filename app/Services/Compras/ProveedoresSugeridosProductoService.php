<?php

namespace App\Services\Compras;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProveedoresSugeridosProductoService
{
    /**
     * @param iterable<int> $productoIds
     * @return Collection<int, Collection<int, object>>
     */
    public function porProducto(iterable $productoIds): Collection
    {
        $ids = collect($productoIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('cotizacion_detalles as d')
            ->join('cotizaciones as c', 'c.id', '=', 'd.cotizacion_id')
            ->join('proveedores as p', 'p.id', '=', 'c.proveedor_id')
            ->whereIn('d.producto_id', $ids)
            ->where('c.estado', '!=', 'ANULADA')
            ->where('p.estado', true)
            ->groupBy([
                'd.producto_id',
                'p.id',
                'p.ruc',
                'p.razon_social',
                'p.nombre_comercial',
                'p.telefono',
                'p.correo',
                'p.contacto',
            ])
            ->select([
                'd.producto_id',
                'p.id as proveedor_id',
                'p.ruc',
                'p.razon_social',
                'p.nombre_comercial',
                'p.telefono',
                'p.correo',
                'p.contacto',
            ])
            ->selectRaw('COUNT(DISTINCT c.id) as cotizaciones_registradas')
            ->selectRaw('MAX(c.fecha_cotizacion) as ultima_cotizacion')
            ->orderByDesc('ultima_cotizacion')
            ->get()
            ->groupBy(fn (object $fila): int => (int) $fila->producto_id)
            ->map(fn (Collection $filas): Collection => $filas->take(6)->values());
    }
}
