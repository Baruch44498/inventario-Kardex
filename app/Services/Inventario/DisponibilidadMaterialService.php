<?php

namespace App\Services\Inventario;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DisponibilidadMaterialService
{
    /**
     * @param iterable<int> $productoIds
     * @return Collection<int, array<string, float>>
     */
    public function resumenesProductos(iterable $productoIds, ?int $ordenId = null): Collection
    {
        $ids = collect($productoIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $inventarios = DB::table('inventarios')
            ->whereIn('producto_id', $ids)
            ->groupBy('producto_id')
            ->selectRaw('producto_id')
            ->selectRaw('COALESCE(SUM(stock_actual), 0) as stock_fisico')
            ->selectRaw('COALESCE(SUM(stock_minimo), 0) as stock_minimo')
            ->get()
            ->keyBy('producto_id');

        $reservas = DB::table('reservas_materiales_orden as r')
            ->join('ordenes_operacion as o', 'o.id', '=', 'r.orden_operacion_id')
            ->whereIn('r.producto_id', $ids)
            ->where('r.estado', 'ACTIVA')
            ->whereIn('o.estado', ['ABIERTA', 'EN_PROCESO'])
            ->groupBy('r.producto_id')
            ->selectRaw('r.producto_id')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN (r.cantidad_reservada - r.cantidad_atendida - r.cantidad_liberada) > 0 '
                . 'THEN (r.cantidad_reservada - r.cantidad_atendida - r.cantidad_liberada) ELSE 0 END), 0) as reservado'
            )
            ->get()
            ->keyBy('producto_id');

        $reservasOrden = collect();
        if ($ordenId) {
            $reservasOrden = DB::table('reservas_materiales_orden')
                ->where('orden_operacion_id', $ordenId)
                ->whereIn('producto_id', $ids)
                ->where('estado', 'ACTIVA')
                ->groupBy('producto_id')
                ->selectRaw('producto_id')
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN (cantidad_reservada - cantidad_atendida - cantidad_liberada) > 0 '
                    . 'THEN (cantidad_reservada - cantidad_atendida - cantidad_liberada) ELSE 0 END), 0) as reservado'
                )
                ->get()
                ->keyBy('producto_id');
        }

        $herramientas = $this->herramientasEnUsoPorProducto($ids);

        return $ids->mapWithKeys(function (int $productoId) use (
            $inventarios,
            $reservas,
            $reservasOrden,
            $herramientas
        ): array {
            $fisico = round((float) ($inventarios->get($productoId)->stock_fisico ?? 0), 3);
            $minimo = round((float) ($inventarios->get($productoId)->stock_minimo ?? 0), 3);
            $reservado = round((float) ($reservas->get($productoId)->reservado ?? 0), 3);
            $reservadoOrden = round((float) ($reservasOrden->get($productoId)->reservado ?? 0), 3);
            $disponible = round($fisico - $reservado, 3);
            $necesidad = max(0, round($reservado + $minimo - $fisico, 3));

            return [$productoId => [
                'stock_fisico' => $fisico,
                'stock_minimo' => $minimo,
                'reservado' => $reservado,
                'reservado_orden' => $reservadoOrden,
                'disponible' => $disponible,
                'necesidad_abastecimiento' => $necesidad,
                'herramientas_en_uso' => round((float) ($herramientas->get($productoId) ?? 0), 3),
            ]];
        });
    }

    /** @return array<string, float> */
    public function resumenProducto(int $productoId, ?int $ordenId = null): array
    {
        return $this->resumenesProductos([$productoId], $ordenId)->get($productoId, [
            'stock_fisico' => 0.0,
            'stock_minimo' => 0.0,
            'reservado' => 0.0,
            'reservado_orden' => 0.0,
            'disponible' => 0.0,
            'necesidad_abastecimiento' => 0.0,
            'herramientas_en_uso' => 0.0,
        ]);
    }

    /**
     * @param Collection<int, int> $productoIds
     * @return Collection<int, float>
     */
    private function herramientasEnUsoPorProducto(Collection $productoIds): Collection
    {
        $retornos = DB::table('nota_ingreso_detalles as d')
            ->join('notas_ingreso as n', 'n.id', '=', 'd.nota_ingreso_id')
            ->where('n.estado', 'CONFIRMADA')
            ->whereNotNull('d.nota_salida_detalle_id')
            ->groupBy('d.nota_salida_detalle_id')
            ->selectRaw('d.nota_salida_detalle_id')
            ->selectRaw('SUM(d.cantidad) as retornado');

        return DB::table('nota_salida_detalles as d')
            ->join('notas_salida as n', 'n.id', '=', 'd.nota_salida_id')
            ->leftJoinSub($retornos, 'ret', fn ($join) => $join
                ->on('ret.nota_salida_detalle_id', '=', 'd.id'))
            ->where('n.estado', 'CONFIRMADA')
            ->where('d.tratamiento', 'USO_TEMPORAL')
            ->whereIn('d.producto_id', $productoIds)
            ->groupBy('d.producto_id')
            ->selectRaw('d.producto_id')
            ->selectRaw('SUM(CASE WHEN (d.cantidad - COALESCE(ret.retornado, 0)) > 0 THEN (d.cantidad - COALESCE(ret.retornado, 0)) ELSE 0 END) as en_uso')
            ->get()
            ->mapWithKeys(fn ($fila): array => [
                (int) $fila->producto_id => (float) $fila->en_uso,
            ]);
    }
}
