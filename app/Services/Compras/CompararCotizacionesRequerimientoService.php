<?php

namespace App\Services\Compras;

use App\Models\CotizacionDetalle;
use App\Models\Requisicion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompararCotizacionesRequerimientoService
{
    /** @return array<string, mixed> */
    public function construir(Requisicion $requerimiento): array
    {
        $requerimiento->load([
            'detalles.producto.unidadMedida',
            'detalles.cotizacionDetalles' => fn ($query) => $query
                ->with(['producto', 'cotizacion.proveedor']),
        ]);

        $compras = $this->comprasPorLinea($requerimiento->detalles->pluck('id'));

        $lineas = $requerimiento->detalles
            ->map(function ($linea) use ($requerimiento, $compras): array {
                $compra = $compras->get((int) $linea->id)?->first();
                $ofertas = $linea->cotizacionDetalles
                    ->filter(fn (CotizacionDetalle $detalle): bool =>
                        (int) $detalle->cotizacion?->requisicion_id === (int) $requerimiento->id
                        && in_array($detalle->tipoVinculacionEfectivo(), ['SOLICITADO', 'ALTERNATIVA'], true)
                        && ! in_array($detalle->cotizacion?->estado, ['ANULADA', 'NO_REQUERIDA', 'NO_UTILIZADA'], true)
                    )
                    ->map(function (CotizacionDetalle $detalle) use ($compra): array {
                        $cotizacion = $detalle->cotizacion;
                        $precioPen = $detalle->precioEquivalentePen();

                        return [
                            'cotizacion_detalle_id' => (int) $detalle->id,
                            'cotizacion_id' => (int) $cotizacion->id,
                            'cotizacion_codigo' => $cotizacion->codigo,
                            'proveedor_id' => (int) $cotizacion->proveedor_id,
                            'proveedor' => $cotizacion->proveedor?->nombreVisible(),
                            'producto_codigo' => $detalle->producto?->codigo,
                            'producto_descripcion' => $detalle->producto?->descripcion,
                            'tipo_vinculacion' => $detalle->tipoVinculacionEfectivo(),
                            'cantidad' => (float) $detalle->cantidad,
                            'moneda' => $cotizacion->moneda,
                            'tipo_cambio' => (float) $cotizacion->tipo_cambio,
                            'precio_unitario' => $detalle->precioFinalUnitario(),
                            'precio_pen' => $precioPen,
                            'total_pen' => $precioPen === null
                                ? null
                                : round((float) $detalle->cantidad * $precioPen, 4),
                            'fecha' => $cotizacion->fecha_cotizacion,
                            'estado_cotizacion' => $cotizacion->estado,
                            'seleccionable' => $compra === null && $cotizacion->estado === 'REGISTRADA',
                            'es_mejor_precio' => false,
                        ];
                    })
                    ->sort(function (array $a, array $b): int {
                        if ($a['precio_pen'] === null) {
                            return $b['precio_pen'] === null ? 0 : 1;
                        }
                        if ($b['precio_pen'] === null) {
                            return -1;
                        }

                        $precio = $a['precio_pen'] <=> $b['precio_pen'];

                        return $precio !== 0
                            ? $precio
                            : strcmp((string) $b['fecha'], (string) $a['fecha']);
                    })
                    ->values();

                $mejorId = $ofertas
                    ->first(fn (array $oferta): bool => $oferta['seleccionable'] && $oferta['precio_pen'] !== null)
                    ['cotizacion_detalle_id'] ?? null;
                $ofertas = $ofertas->map(function (array $oferta) use ($mejorId): array {
                    $oferta['es_mejor_precio'] = $mejorId !== null
                        && $oferta['cotizacion_detalle_id'] === $mejorId;

                    return $oferta;
                });

                return [
                    'requisicion_detalle_id' => (int) $linea->id,
                    'producto_id' => (int) $linea->producto_id,
                    'codigo' => $linea->producto?->codigo,
                    'descripcion' => $linea->producto?->descripcion,
                    'unidad' => $linea->producto?->unidadMedida?->abreviatura
                        ?? $linea->producto?->unidadMedida?->codigo,
                    'cantidad_solicitada' => (float) $linea->cantidad_solicitada,
                    'ofertas' => $ofertas,
                    'compra' => $compra,
                    'mejor_oferta_id' => $mejorId,
                ];
            })
            ->values();

        return [
            'lineas' => $lineas,
            'total_lineas' => $lineas->count(),
            'lineas_compradas' => $lineas->whereNotNull('compra')->count(),
            'lineas_cotizables' => $lineas->filter(
                fn (array $linea): bool => $linea['compra'] === null
                    && $linea['ofertas']->contains('seleccionable', true)
            )->count(),
            'lineas_sin_oferta' => $lineas->filter(
                fn (array $linea): bool => $linea['compra'] === null
                    && ! $linea['ofertas']->contains('seleccionable', true)
            )->count(),
        ];
    }

    /**
     * @param array<int|string, int|string> $selecciones
     * @return Collection<int, CotizacionDetalle>
     */
    public function validarSeleccion(Requisicion $requerimiento, array $selecciones): Collection
    {
        $selecciones = collect($selecciones)
            ->mapWithKeys(fn ($detalleId, $lineaId): array => [(int) $lineaId => (int) $detalleId]);
        $lineaIds = $requerimiento->detalles()->pluck('id')->map(fn ($id): int => (int) $id);

        if ($selecciones->keys()->diff($lineaIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'selecciones' => 'La selección contiene líneas que no pertenecen al requerimiento.',
            ]);
        }

        $detalles = CotizacionDetalle::query()
            ->with(['cotizacion', 'requisicionDetalle'])
            ->whereIn('id', $selecciones->values())
            ->get()
            ->keyBy('id');

        if ($detalles->count() !== $selecciones->count()) {
            throw ValidationException::withMessages([
                'selecciones' => 'Una de las ofertas seleccionadas ya no está disponible.',
            ]);
        }

        $compradas = $this->comprasPorLinea($selecciones->keys());
        if ($compradas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'selecciones' => 'Uno de los productos seleccionados ya tiene una orden de compra vigente.',
            ]);
        }

        return $selecciones
            ->map(function (int $detalleId, int $lineaId) use ($detalles, $requerimiento): CotizacionDetalle {
                $detalle = $detalles->get($detalleId);

                if (
                    ! $detalle
                    || (int) $detalle->requisicion_detalle_id !== $lineaId
                    || (int) $detalle->cotizacion?->requisicion_id !== (int) $requerimiento->id
                    || $detalle->cotizacion?->estado !== 'REGISTRADA'
                    || ! in_array($detalle->tipoVinculacionEfectivo(), ['SOLICITADO', 'ALTERNATIVA'], true)
                ) {
                    throw ValidationException::withMessages([
                        "selecciones.{$lineaId}" => 'La oferta elegida no corresponde a esta línea o dejó de estar disponible.',
                    ]);
                }

                return $detalle;
            })
            ->values();
    }

    /**
     * @param iterable<int> $lineaIds
     * @return Collection<int, Collection<int, object>>
     */
    public function comprasPorLinea(iterable $lineaIds): Collection
    {
        $ids = collect($lineaIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('solicitud_compra_detalles as sd')
            ->join('cotizacion_detalles as cd', 'cd.id', '=', 'sd.cotizacion_detalle_id')
            ->join('solicitudes_compra as sc', 'sc.id', '=', 'sd.solicitud_compra_id')
            ->join('ordenes_compra as oc', 'oc.solicitud_compra_id', '=', 'sc.id')
            ->join('proveedores as p', 'p.id', '=', 'oc.proveedor_id')
            ->whereIn('cd.requisicion_detalle_id', $ids)
            ->where('oc.estado', '!=', 'ANULADA')
            ->select([
                'cd.requisicion_detalle_id',
                'oc.id as orden_id',
                'oc.codigo as orden_codigo',
                'oc.estado as orden_estado',
                'p.id as proveedor_id',
                'p.razon_social',
                'p.nombre_comercial',
            ])
            ->orderBy('oc.id')
            ->get()
            ->groupBy(fn (object $fila): int => (int) $fila->requisicion_detalle_id);
    }
}
