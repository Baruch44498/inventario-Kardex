<?php

namespace App\Services\Inventario;

use App\Models\FacturaProveedorDetalle;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\NotaIngreso;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\User;
use App\Services\Compras\SeguimientoAbastecimientoRequerimientoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnularNotaIngresoService
{
    public function __construct(
        private SeguimientoAbastecimientoRequerimientoService $seguimiento,
        private EvaluarAlertasStockService $alertas
    ) {}

    public function anular(
        NotaIngreso $notaIngreso,
        User $usuario,
        string $motivo
    ): NotaIngreso {
        return DB::transaction(function () use (
            $notaIngreso,
            $usuario,
            $motivo
        ): NotaIngreso {
            $nota = NotaIngreso::query()
                ->with('detalles')
                ->lockForUpdate()
                ->findOrFail($notaIngreso->id);

            if ($nota->estado !== 'CONFIRMADA') {
                throw ValidationException::withMessages([
                    'estado' =>
                        'Solo se puede anular una nota de ingreso confirmada.',
                ]);
            }

            if ($nota->motivo_ingreso !== 'COMPRA' || ! $nota->orden_compra_id) {
                throw ValidationException::withMessages([
                    'estado' =>
                        'Este correctivo solo permite anular recepciones de compra.',
                ]);
            }

            if (trim($motivo) === '') {
                throw ValidationException::withMessages([
                    'motivo_anulacion' =>
                        'El motivo de anulación es obligatorio.',
                ]);
            }

            $detalleIds = $nota->detalles->pluck('id');
            $tieneFacturaActiva = FacturaProveedorDetalle::query()
                ->whereIn('nota_ingreso_detalle_id', $detalleIds)
                ->whereHas(
                    'facturaProveedor',
                    fn ($factura) => $factura->where('estado', '!=', 'ANULADA')
                )
                ->exists();

            if ($tieneFacturaActiva) {
                throw ValidationException::withMessages([
                    'estado' =>
                        'La recepción está conciliada con una factura activa. Anula primero la factura vinculada.',
                ]);
            }

            $orden = OrdenCompra::query()
                ->with('detalles')
                ->lockForUpdate()
                ->findOrFail($nota->orden_compra_id);

            if ($orden->estado === 'ANULADA') {
                throw ValidationException::withMessages([
                    'estado' =>
                        'La orden de compra está anulada y requiere revisión manual.',
                ]);
            }

            $movimientos = $this->movimientosOriginales($nota);
            $this->validarReversionInventario($nota, $movimientos);
            $inventarios = $this->revertirInventario(
                $nota,
                $movimientos,
                $usuario,
                $motivo
            );

            $detallesOrden = collect();
            foreach ($nota->detalles as $detalle) {
                $detalleOrden = OrdenCompraDetalle::query()
                    ->whereKey($detalle->orden_compra_detalle_id)
                    ->where('orden_compra_id', $orden->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((float) $detalleOrden->cantidad_recibida + 0.0001
                    < (float) $detalle->cantidad) {
                    throw ValidationException::withMessages([
                        'estado' =>
                            'La cantidad recibida de la OC ya no coincide con esta nota. Requiere revisión manual.',
                    ]);
                }

                $detalleOrden->update([
                    'cantidad_recibida' => max(0, round(
                        (float) $detalleOrden->cantidad_recibida
                            - (float) $detalle->cantidad,
                        3
                    )),
                ]);
                $detallesOrden->push($detalleOrden->fresh());
            }

            $this->actualizarEstadoOrden($orden);

            $detallesOrden
                ->unique('id')
                ->each(fn (OrdenCompraDetalle $detalle) =>
                    $this->seguimiento
                        ->sincronizarCantidadAtendidaPorOrdenDetalle($detalle)
                );

            $nota->update([
                'estado' => 'ANULADA',
                'anulado_por' => $usuario->id,
                'anulado_en' => now(),
                'motivo_anulacion' => trim($motivo),
            ]);

            $inventarios
                ->unique('id')
                ->each(fn (Inventario $inventario) =>
                    $this->alertas
                        ->evaluarInventario($inventario->fresh(), $usuario)
                );

            return $nota->fresh([
                'ordenCompra.detalles',
                'anulador',
                'detalles.producto',
                'detalles.repisa',
            ]);
        });
    }

    private function movimientosOriginales(NotaIngreso $nota): Collection
    {
        $movimientos = MovimientoInventario::query()
            ->where('origen_tipo', 'NOTA_INGRESO')
            ->where('origen_id', $nota->id)
            ->whereIn('origen_detalle_id', $nota->detalles->pluck('id'))
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        $detallesConStock = $nota->detalles
            ->filter(fn ($detalle) => (bool) $detalle->afecta_stock);

        if ($movimientos->pluck('origen_detalle_id')->unique()->count()
            !== $detallesConStock->count()) {
            throw ValidationException::withMessages([
                'estado' =>
                    'No se encontraron todos los movimientos originales de la recepción. Requiere revisión manual.',
            ]);
        }

        return $movimientos;
    }

    private function validarReversionInventario(
        NotaIngreso $nota,
        Collection $movimientos
    ): void {
        foreach ($movimientos->groupBy('inventario_id') as $inventarioId => $grupo) {
            $primerMovimientoId = (int) $grupo->min('id');
            $hayMovimientoPosterior = MovimientoInventario::query()
                ->where('inventario_id', $inventarioId)
                ->where('id', '>', $primerMovimientoId)
                ->where(function ($query) use ($nota): void {
                    $query
                        ->where('origen_tipo', '!=', 'NOTA_INGRESO')
                        ->orWhere('origen_id', '!=', $nota->id);
                })
                ->exists();

            if ($hayMovimientoPosterior) {
                throw ValidationException::withMessages([
                    'estado' =>
                        'Uno de los productos ya tiene movimientos posteriores. La recepción no puede anularse automáticamente.',
                ]);
            }

            $ultimo = $grupo->sortByDesc('id')->first();
            $inventario = Inventario::query()
                ->whereKey($inventarioId)
                ->lockForUpdate()
                ->firstOrFail();

            if (abs(
                (float) $inventario->stock_actual
                    - (float) $ultimo->stock_posterior
            ) > 0.0001 || abs(
                (float) $inventario->costo_promedio_soles
                    - (float) $ultimo->costo_promedio_nuevo
            ) > 0.0001) {
                throw ValidationException::withMessages([
                    'estado' =>
                        'El stock o su costo actual ya no coincide con esta recepción. Requiere revisión manual.',
                ]);
            }
        }
    }

    private function revertirInventario(
        NotaIngreso $nota,
        Collection $movimientos,
        User $usuario,
        string $motivo
    ): Collection {
        $inventarios = collect();

        foreach ($movimientos as $movimiento) {
            $inventario = Inventario::query()
                ->whereKey($movimiento->inventario_id)
                ->lockForUpdate()
                ->firstOrFail();

            $stockAnterior = round((float) $inventario->stock_actual, 3);
            $costoAnterior = round(
                (float) $inventario->costo_promedio_soles,
                4
            );
            $stockPosterior = round((float) $movimiento->stock_anterior, 3);
            $costoPosterior = round(
                (float) $movimiento->costo_promedio_anterior,
                4
            );

            $inventario->update([
                'stock_actual' => $stockPosterior,
                'costo_promedio_soles' => $costoPosterior,
            ]);

            MovimientoInventario::create([
                'inventario_id' => $inventario->id,
                'producto_id' => $movimiento->producto_id,
                'repisa_id' => $movimiento->repisa_id,
                'tipo_movimiento' => 'SALIDA',
                'motivo' => 'ANULACION_COMPRA',
                'origen_tipo' => 'ANULACION_NOTA_INGRESO',
                'origen_id' => $nota->id,
                'origen_detalle_id' => $movimiento->origen_detalle_id,
                'cantidad' => $movimiento->cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_posterior' => $stockPosterior,
                'costo_unitario' => $movimiento->costo_unitario,
                'costo_promedio_anterior' => $costoAnterior,
                'costo_promedio_nuevo' => $costoPosterior,
                'fecha_movimiento' => now(),
                'observacion' => trim($motivo),
                'registrado_por' => $usuario->id,
            ]);

            $inventarios->push($inventario);
        }

        return $inventarios;
    }

    private function actualizarEstadoOrden(OrdenCompra $orden): void
    {
        $orden->load('detalles');
        $cantidadRecibida = (float) $orden->detalles->sum('cantidad_recibida');
        $completa = $orden->detalles->isNotEmpty()
            && $orden->detalles->every(
                fn ($detalle) => (float) $detalle->cantidad_recibida
                    >= (float) $detalle->cantidad_ordenada
            );

        $orden->update([
            'estado' => $completa
                ? 'RECIBIDA'
                : ($cantidadRecibida > 0 ? 'PARCIALMENTE_RECIBIDA' : 'APROBADA'),
        ]);
    }
}
