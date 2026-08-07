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
    public function __construct(private ReservaMaterialService $reservas) {}

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

            $detalleIds = $nota->detalles->pluck('id');
            $tieneRetornos = DB::table('nota_ingreso_detalles as d')
                ->join('notas_ingreso as n', 'n.id', '=', 'd.nota_ingreso_id')
                ->whereIn('d.nota_salida_detalle_id', $detalleIds)
                ->where('n.estado', 'CONFIRMADA')
                ->exists();

            $proformaDetalleIds = $nota->detalles
                ->pluck('proforma_detalle_id')
                ->filter();
            $tieneReposiciones = $proformaDetalleIds->isNotEmpty()
                && DB::table('proforma_prestamo_reposiciones')
                ->whereIn('proforma_detalle_id', $proformaDetalleIds)
                ->exists();

            if ($tieneRetornos || $tieneReposiciones) {
                throw ValidationException::withMessages([
                    'estado' =>
                    'La nota ya tiene devoluciones o reposiciones registradas. No puede anularse automáticamente.',
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

                if (
                    $detalle->reserva_material_orden_id
                    && (float) $detalle->cantidad_aplicada_reserva > 0
                ) {
                    $this->reservas->revertirSalida(
                        (int) $detalle->reserva_material_orden_id,
                        (float) $detalle->cantidad_aplicada_reserva,
                        $usuario
                    );
                }
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
