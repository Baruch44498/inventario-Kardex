<?php

namespace App\Services\Inventario;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\NotaSalida;
use App\Models\OrdenOperacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarNotaSalidaService
{
    public function registrarYConfirmar(array $datos, User $usuario): NotaSalida
    {
        return DB::transaction(function () use ($datos, $usuario) {
            $orden = OrdenOperacion::query()
                ->lockForUpdate()
                ->findOrFail($datos['orden_operacion_id']);

            if (in_array($orden->estado, ['ANULADA', 'CERRADA'], true)) {
                throw ValidationException::withMessages([
                    'orden_operacion_id' =>
                        'No se pueden registrar salidas para una orden anulada o cerrada.',
                ]);
            }

            if (empty($datos['detalles'])) {
                throw ValidationException::withMessages([
                    'detalles' => 'La nota de salida debe contener al menos un detalle.',
                ]);
            }

            $nota = NotaSalida::create([
                'orden_operacion_id' => $orden->id,
                'codigo' => $datos['codigo'],
                'fecha_salida' => $datos['fecha_salida'],
                'entregado_a' => $datos['entregado_a'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
                'estado' => 'CONFIRMADA',
                'registrado_por' => $usuario->id,
                'confirmado_por' => $usuario->id,
                'confirmado_en' => now(),
            ]);

            foreach ($datos['detalles'] as $item) {
                $cantidad = round((float) $item['cantidad'], 3);

                if ($cantidad <= 0) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'La cantidad de salida debe ser mayor que cero.',
                    ]);
                }

                $inventario = Inventario::query()
                    ->where('producto_id', $item['producto_id'])
                    ->where('repisa_id', $item['repisa_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$inventario) {
                    throw ValidationException::withMessages([
                        'inventario' =>
                            'No existe inventario para el producto y la repisa indicados.',
                    ]);
                }

                $stockAnterior = round((float) $inventario->stock_actual, 3);

                if ($cantidad > $stockAnterior) {
                    throw ValidationException::withMessages([
                        'cantidad' =>
                            "Stock insuficiente. Disponible: {$stockAnterior}.",
                    ]);
                }

                $costoPromedio = round(
                    (float) $inventario->costo_promedio_soles,
                    4
                );

                $stockPosterior = round($stockAnterior - $cantidad, 3);

                $detalle = $nota->detalles()->create([
                    'producto_id' => $item['producto_id'],
                    'repisa_id' => $item['repisa_id'],
                    'cantidad' => $cantidad,
                    'costo_unitario_promedio' => $costoPromedio,
                    'subtotal' => round($cantidad * $costoPromedio, 4),
                    'observacion' => $item['observacion'] ?? null,
                ]);

                $inventario->update([
                    'stock_actual' => $stockPosterior,
                ]);

                MovimientoInventario::create([
                    'inventario_id' => $inventario->id,
                    'producto_id' => $item['producto_id'],
                    'repisa_id' => $item['repisa_id'],
                    'tipo_movimiento' => 'SALIDA',
                    'motivo' => 'CONSUMO_ORDEN',
                    'origen_tipo' => 'NOTA_SALIDA',
                    'origen_id' => $nota->id,
                    'origen_detalle_id' => $detalle->id,
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_posterior' => $stockPosterior,
                    'costo_unitario' => $costoPromedio,
                    'costo_promedio_anterior' => $costoPromedio,
                    'costo_promedio_nuevo' => $costoPromedio,
                    'fecha_movimiento' => now(),
                    'observacion' => $item['observacion'] ?? null,
                    'registrado_por' => $usuario->id,
                ]);
                app(EvaluarAlertasStockService::class)
                    ->evaluarInventario($inventario->refresh(), $usuario);
            }

            if ($orden->estado === 'ABIERTA') {
                $orden->update([
                    'estado' => 'EN_PROCESO',
                ]);
            }

            return $nota->load([
                'ordenOperacion.tipoOrden',
                'ordenOperacion.cliente',
                'ordenOperacion.vehiculo',
                'registrador',
                'confirmador',
                'detalles.producto',
                'detalles.repisa',
            ]);
        });
    }
}
