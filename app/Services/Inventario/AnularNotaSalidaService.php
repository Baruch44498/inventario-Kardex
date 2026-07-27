<?php

namespace App\Services\Inventario;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\NotaSalida;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnularNotaSalidaService
{
    public function anular(
        NotaSalida $notaSalida,
        User $usuario,
        string $motivo
    ): NotaSalida {
        return DB::transaction(function () use (
            $notaSalida,
            $usuario,
            $motivo
        ) {
            $nota = NotaSalida::query()
                ->with('detalles')
                ->lockForUpdate()
                ->findOrFail($notaSalida->id);

            if ($nota->estado !== 'CONFIRMADA') {
                throw ValidationException::withMessages([
                    'estado' =>
                        'Solo se puede anular una nota de salida confirmada.',
                ]);
            }

            if (trim($motivo) === '') {
                throw ValidationException::withMessages([
                    'motivo_anulacion' =>
                        'El motivo de anulación es obligatorio.',
                ]);
            }

            foreach ($nota->detalles as $detalle) {
                $inventario = Inventario::query()
                    ->where('producto_id', $detalle->producto_id)
                    ->where('repisa_id', $detalle->repisa_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $cantidad = round((float) $detalle->cantidad, 3);
                $stockAnterior = round((float) $inventario->stock_actual, 3);
                $costoAnterior = round(
                    (float) $inventario->costo_promedio_soles,
                    4
                );
                $costoDevuelto = round(
                    (float) $detalle->costo_unitario_promedio,
                    4
                );

                $stockPosterior = round($stockAnterior + $cantidad, 3);

                $costoNuevo = $stockPosterior > 0
                    ? round(
                        (
                            ($stockAnterior * $costoAnterior)
                            + ($cantidad * $costoDevuelto)
                        ) / $stockPosterior,
                        4
                    )
                    : 0;

                $inventario->update([
                    'stock_actual' => $stockPosterior,
                    'costo_promedio_soles' => $costoNuevo,
                ]);

                MovimientoInventario::create([
                    'inventario_id' => $inventario->id,
                    'producto_id' => $detalle->producto_id,
                    'repisa_id' => $detalle->repisa_id,
                    'tipo_movimiento' => 'ENTRADA',
                    'motivo' => 'ANULACION_SALIDA',
                    'origen_tipo' => 'ANULACION_NOTA_SALIDA',
                    'origen_id' => $nota->id,
                    'origen_detalle_id' => $detalle->id,
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_posterior' => $stockPosterior,
                    'costo_unitario' => $costoDevuelto,
                    'costo_promedio_anterior' => $costoAnterior,
                    'costo_promedio_nuevo' => $costoNuevo,
                    'fecha_movimiento' => now(),
                    'observacion' => $motivo,
                    'registrado_por' => $usuario->id,
                ]);
                app(EvaluarAlertasStockService::class)
                     ->evaluarInventario($inventario->refresh(), $usuario);
            }

            $nota->update([
                'estado' => 'ANULADA',
                'anulado_por' => $usuario->id,
                'anulado_en' => now(),
                'motivo_anulacion' => $motivo,
            ]);

            return $nota->fresh([
                'ordenOperacion',
                'anulador',
                'detalles.producto',
                'detalles.repisa',
            ]);
        });
    }
}
