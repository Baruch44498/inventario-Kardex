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

    /**
     * Propone una partición sin repetir productos, priorizando al proveedor que
     * cubre la mayor cantidad pendiente y usando la experiencia más reciente
     * como desempate.
     *
     * @param iterable<int> $productoIds
     * @return array{grupos: Collection<int, array<string, mixed>>, sin_proveedor: Collection<int, int>, cobertura_total: bool}
     */
    public function coberturaSugerida(
        iterable $productoIds,
        ?Collection $proveedoresPorProducto = null
    ): array {
        $ids = collect($productoIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $proveedoresPorProducto ??= $this->porProducto($ids);

        $candidatos = $proveedoresPorProducto
            ->flatten(1)
            ->groupBy(fn (object $fila): int => (int) $fila->proveedor_id)
            ->map(function (Collection $filas): array {
                $primero = $filas->first();

                return [
                    'proveedor_id' => (int) $primero->proveedor_id,
                    'nombre' => $primero->nombre_comercial ?: $primero->razon_social,
                    'razon_social' => $primero->razon_social,
                    'ruc' => $primero->ruc,
                    'telefono' => $primero->telefono,
                    'correo' => $primero->correo,
                    'contacto' => $primero->contacto,
                    'producto_ids' => $filas
                        ->pluck('producto_id')
                        ->map(fn ($id): int => (int) $id)
                        ->unique()
                        ->values(),
                    'cotizaciones_registradas' => (int) $filas->sum('cotizaciones_registradas'),
                    'ultima_cotizacion' => $filas->max('ultima_cotizacion'),
                ];
            })
            ->values();

        $pendientes = $ids;
        $grupos = collect();

        while ($pendientes->isNotEmpty()) {
            $mejor = $candidatos
                ->map(function (array $proveedor) use ($pendientes): array {
                    $cubiertos = $proveedor['producto_ids']
                        ->intersect($pendientes)
                        ->values();

                    return [...$proveedor, 'cubiertos' => $cubiertos];
                })
                ->filter(fn (array $proveedor): bool => $proveedor['cubiertos']->isNotEmpty())
                ->sort(function (array $a, array $b): int {
                    $porCobertura = $b['cubiertos']->count() <=> $a['cubiertos']->count();
                    if ($porCobertura !== 0) {
                        return $porCobertura;
                    }

                    $porFecha = strcmp(
                        (string) ($b['ultima_cotizacion'] ?? ''),
                        (string) ($a['ultima_cotizacion'] ?? '')
                    );
                    if ($porFecha !== 0) {
                        return $porFecha;
                    }

                    $porExperiencia = $b['cotizaciones_registradas'] <=> $a['cotizaciones_registradas'];

                    return $porExperiencia !== 0
                        ? $porExperiencia
                        : $a['proveedor_id'] <=> $b['proveedor_id'];
                })
                ->first();

            if (! $mejor) {
                break;
            }

            $grupos->push([
                'proveedor_id' => $mejor['proveedor_id'],
                'nombre' => $mejor['nombre'],
                'razon_social' => $mejor['razon_social'],
                'ruc' => $mejor['ruc'],
                'telefono' => $mejor['telefono'],
                'correo' => $mejor['correo'],
                'contacto' => $mejor['contacto'],
                'producto_ids' => $mejor['cubiertos'],
                'cotizaciones_registradas' => $mejor['cotizaciones_registradas'],
                'ultima_cotizacion' => $mejor['ultima_cotizacion'],
            ]);

            $pendientes = $pendientes->diff($mejor['cubiertos'])->values();
            $candidatos = $candidatos
                ->reject(fn (array $proveedor): bool => $proveedor['proveedor_id'] === $mejor['proveedor_id'])
                ->values();
        }

        return [
            'grupos' => $grupos,
            'sin_proveedor' => $pendientes,
            'cobertura_total' => $ids->isNotEmpty() && $pendientes->isEmpty(),
        ];
    }
}
