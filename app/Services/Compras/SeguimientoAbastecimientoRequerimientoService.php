<?php

namespace App\Services\Compras;

use App\Models\OrdenCompraDetalle;
use App\Models\Requisicion;
use App\Models\RequisicionDetalle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SeguimientoAbastecimientoRequerimientoService
{
    /** @return array<string, mixed> */
    public function construir(Requisicion $requerimiento): array
    {
        $requerimiento->loadMissing('detalles.producto.unidadMedida');
        $detalleIds = $requerimiento->detalles
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($detalleIds->isEmpty()) {
            return $this->respuestaVacia();
        }

        $ofertas = $this->ofertasPorLinea($detalleIds);
        $ordenes = $this->ordenesPorLinea($detalleIds);
        $ordenDetalleIds = $ordenes
            ->flatten(1)
            ->pluck('orden_compra_detalle_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $recepciones = $this->recepcionesPorOrdenDetalle($ordenDetalleIds);

        $lineas = $requerimiento->detalles
            ->map(function (RequisicionDetalle $detalle) use ($ofertas, $ordenes, $recepciones): array {
                $ofertasLinea = $ofertas->get((int) $detalle->id, collect());
                $ordenesLinea = $ordenes->get((int) $detalle->id, collect())
                    ->map(function (object $orden) use ($recepciones): array {
                        return [
                            'orden_id' => (int) $orden->orden_id,
                            'orden_codigo' => $orden->orden_codigo,
                            'orden_estado' => $orden->orden_estado,
                            'proveedor' => $orden->nombre_comercial ?: $orden->razon_social,
                            'cantidad_ordenada' => (float) $orden->cantidad_ordenada,
                            'cantidad_recibida' => (float) $orden->cantidad_recibida,
                            'recepciones' => $recepciones->get(
                                (int) $orden->orden_compra_detalle_id,
                                collect()
                            ),
                        ];
                    })
                    ->values();

                $cantidadOrdenada = round((float) $ordenesLinea->sum('cantidad_ordenada'), 3);
                $cantidadRecibida = round((float) $ordenesLinea->sum('cantidad_recibida'), 3);
                $cantidadSolicitada = (float) $detalle->cantidad_solicitada;
                $estado = $this->estadoLinea(
                    $cantidadSolicitada,
                    $cantidadOrdenada,
                    $cantidadRecibida,
                    $ofertasLinea
                );

                return [
                    'requisicion_detalle_id' => (int) $detalle->id,
                    'producto_id' => (int) $detalle->producto_id,
                    'codigo' => $detalle->producto?->codigo,
                    'descripcion' => $detalle->producto?->descripcion,
                    'unidad' => $detalle->producto?->unidadMedida?->abreviatura
                        ?? $detalle->producto?->unidadMedida?->codigo,
                    'cantidad_solicitada' => $cantidadSolicitada,
                    'cantidad_ordenada' => $cantidadOrdenada,
                    'cantidad_recibida' => $cantidadRecibida,
                    'cantidad_pendiente_recibir' => max(
                        0,
                        round($cantidadSolicitada - $cantidadRecibida, 3)
                    ),
                    'avance_porcentaje' => $cantidadSolicitada > 0
                        ? min(100, round(($cantidadRecibida / $cantidadSolicitada) * 100, 2))
                        : 0,
                    'ofertas' => $ofertasLinea,
                    'ordenes' => $ordenesLinea,
                    'estado' => $estado,
                    'estado_visible' => $this->estadoVisible($estado),
                    'estado_clase' => $this->estadoClase($estado),
                ];
            })
            ->values();

        $conteos = $lineas->countBy('estado');
        $recibidas = (int) $conteos->get('RECIBIDO', 0);

        return [
            'lineas' => $lineas,
            'total_lineas' => $lineas->count(),
            'pendientes_cotizar' => (int) $conteos->get('PENDIENTE_COTIZAR', 0),
            'cotizadas' => (int) $conteos->get('COTIZADO', 0),
            'ordenadas' => (int) $conteos->get('ORDENADO', 0),
            'parciales' => (int) $conteos->get('PARCIALMENTE_RECIBIDO', 0),
            'recibidas' => $recibidas,
            'avance_porcentaje' => $lineas->isNotEmpty()
                ? round((float) $lineas->avg('avance_porcentaje'), 2)
                : 0,
        ];
    }

    public function sincronizarCantidadAtendidaPorOrdenDetalle(OrdenCompraDetalle $ordenDetalle): void
    {
        $requisicionDetalleId = DB::table('solicitud_compra_detalles as sd')
            ->join('cotizacion_detalles as cd', 'cd.id', '=', 'sd.cotizacion_detalle_id')
            ->where('sd.id', $ordenDetalle->solicitud_compra_detalle_id)
            ->value('cd.requisicion_detalle_id');

        if (! $requisicionDetalleId) {
            return;
        }

        $requisicionDetalle = RequisicionDetalle::query()
            ->whereKey($requisicionDetalleId)
            ->lockForUpdate()
            ->first();

        if (! $requisicionDetalle) {
            return;
        }

        $cantidadRecibida = (float) DB::table('orden_compra_detalles as od')
            ->join('solicitud_compra_detalles as sd', 'sd.id', '=', 'od.solicitud_compra_detalle_id')
            ->join('cotizacion_detalles as cd', 'cd.id', '=', 'sd.cotizacion_detalle_id')
            ->join('ordenes_compra as oc', 'oc.id', '=', 'od.orden_compra_id')
            ->where('cd.requisicion_detalle_id', $requisicionDetalle->id)
            ->where('oc.estado', '!=', 'ANULADA')
            ->sum('od.cantidad_recibida');

        $requisicionDetalle->update([
            'cantidad_atendida' => min(
                (float) $requisicionDetalle->cantidad_solicitada,
                round($cantidadRecibida, 3)
            ),
        ]);

        $this->sincronizarEstadoRequerimiento((int) $requisicionDetalle->requisicion_id);
    }

    public function sincronizarEstadoRequerimiento(int $requisicionId): void
    {
        $requerimiento = Requisicion::query()
            ->whereKey($requisicionId)
            ->lockForUpdate()
            ->first();

        if (! $requerimiento) {
            return;
        }

        $resumen = RequisicionDetalle::query()
            ->where('requisicion_id', $requerimiento->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN cantidad_atendida > 0 THEN 1 ELSE 0 END) as con_recepcion')
            ->selectRaw('SUM(CASE WHEN cantidad_atendida + 0.0001 >= cantidad_solicitada THEN 1 ELSE 0 END) as completas')
            ->first();

        $total = (int) ($resumen->total ?? 0);
        $completas = (int) ($resumen->completas ?? 0);
        $conRecepcion = (int) ($resumen->con_recepcion ?? 0);
        $estado = $total > 0 && $completas === $total
            ? 'COMPLETO'
            : ($conRecepcion > 0 ? 'PARCIAL' : 'PENDIENTE');

        $requerimiento->update([
            'estado_abastecimiento' => $estado,
            'abastecido_en' => $estado === 'COMPLETO'
                ? ($requerimiento->abastecido_en ?? now())
                : null,
        ]);
    }

    /** @param Collection<int, int> $detalleIds */
    private function ofertasPorLinea(Collection $detalleIds): Collection
    {
        return DB::table('cotizacion_detalles as cd')
            ->join('cotizaciones as c', 'c.id', '=', 'cd.cotizacion_id')
            ->join('proveedores as p', 'p.id', '=', 'c.proveedor_id')
            ->whereIn('cd.requisicion_detalle_id', $detalleIds)
            ->whereNotIn('c.estado', ['ANULADA', 'NO_REQUERIDA', 'NO_UTILIZADA'])
            ->where(function ($query): void {
                $query
                    ->whereIn('cd.tipo_vinculacion', ['SOLICITADO', 'ALTERNATIVA'])
                    ->orWhereNull('cd.tipo_vinculacion');
            })
            ->get([
                'cd.requisicion_detalle_id',
                'c.id as cotizacion_id',
                'c.codigo as cotizacion_codigo',
                'c.estado as cotizacion_estado',
                'p.id as proveedor_id',
                'p.razon_social',
                'p.nombre_comercial',
            ])
            ->groupBy(fn (object $fila): int => (int) $fila->requisicion_detalle_id);
    }

    /** @param Collection<int, int> $detalleIds */
    private function ordenesPorLinea(Collection $detalleIds): Collection
    {
        return DB::table('orden_compra_detalles as od')
            ->join('solicitud_compra_detalles as sd', 'sd.id', '=', 'od.solicitud_compra_detalle_id')
            ->join('cotizacion_detalles as cd', 'cd.id', '=', 'sd.cotizacion_detalle_id')
            ->join('ordenes_compra as oc', 'oc.id', '=', 'od.orden_compra_id')
            ->join('proveedores as p', 'p.id', '=', 'oc.proveedor_id')
            ->whereIn('cd.requisicion_detalle_id', $detalleIds)
            ->where('oc.estado', '!=', 'ANULADA')
            ->get([
                'cd.requisicion_detalle_id',
                'od.id as orden_compra_detalle_id',
                'od.cantidad_ordenada',
                'od.cantidad_recibida',
                'oc.id as orden_id',
                'oc.codigo as orden_codigo',
                'oc.estado as orden_estado',
                'p.razon_social',
                'p.nombre_comercial',
            ])
            ->groupBy(fn (object $fila): int => (int) $fila->requisicion_detalle_id);
    }

    /** @param Collection<int, int> $ordenDetalleIds */
    private function recepcionesPorOrdenDetalle(Collection $ordenDetalleIds): Collection
    {
        if ($ordenDetalleIds->isEmpty()) {
            return collect();
        }

        return DB::table('nota_ingreso_detalles as nd')
            ->join('notas_ingreso as ni', 'ni.id', '=', 'nd.nota_ingreso_id')
            ->whereIn('nd.orden_compra_detalle_id', $ordenDetalleIds)
            ->where('ni.estado', 'CONFIRMADA')
            ->get([
                'nd.orden_compra_detalle_id',
                'ni.id as nota_id',
                'ni.codigo as nota_codigo',
                'ni.fecha_ingreso',
                'nd.cantidad',
            ])
            ->groupBy(fn (object $fila): int => (int) $fila->orden_compra_detalle_id);
    }

    private function estadoLinea(
        float $solicitada,
        float $ordenada,
        float $recibida,
        Collection $ofertas
    ): string
    {
        if ($solicitada > 0 && $recibida + 0.0001 >= $solicitada) {
            return 'RECIBIDO';
        }

        if ($recibida > 0) {
            return 'PARCIALMENTE_RECIBIDO';
        }

        if ($ordenada > 0) {
            return 'ORDENADO';
        }

        return $ofertas->isNotEmpty() ? 'COTIZADO' : 'PENDIENTE_COTIZAR';
    }

    private function estadoVisible(string $estado): string
    {
        return match ($estado) {
            'RECIBIDO' => 'Recibido completamente',
            'PARCIALMENTE_RECIBIDO' => 'Recepción parcial',
            'ORDENADO' => 'Orden de compra emitida',
            'COTIZADO' => 'Con ofertas',
            default => 'Pendiente de cotizar',
        };
    }

    private function estadoClase(string $estado): string
    {
        return match ($estado) {
            'RECIBIDO' => 'success',
            'PARCIALMENTE_RECIBIDO', 'COTIZADO' => 'warning',
            'ORDENADO' => 'info',
            default => 'neutral',
        };
    }

    /** @return array<string, mixed> */
    private function respuestaVacia(): array
    {
        return [
            'lineas' => collect(),
            'total_lineas' => 0,
            'pendientes_cotizar' => 0,
            'cotizadas' => 0,
            'ordenadas' => 0,
            'parciales' => 0,
            'recibidas' => 0,
            'avance_porcentaje' => 0,
        ];
    }
}
