<?php

namespace App\Services\Inventario;

use App\Models\AlertaStock;
use App\Models\Inventario;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EvaluarAlertasStockService
{
    public function evaluarInventario(
        Inventario $inventario,
        ?User $usuario = null
    ): ?AlertaStock {
        return DB::transaction(function () use ($inventario, $usuario) {
            $inventarioActual = Inventario::query()
                ->with(['producto', 'repisa'])
                ->lockForUpdate()
                ->findOrFail($inventario->id);

            $stockActual = round(
                (float) $inventarioActual->stock_actual,
                3
            );

            $stockMinimo = round(
                (float) $inventarioActual->stock_minimo,
                3
            );

            $tipoAlerta = null;
            $nivel = null;

            if ($stockActual <= 0) {
                $tipoAlerta = 'SIN_STOCK';
                $nivel = 'CRITICA';
            } elseif ($stockActual <= $stockMinimo) {
                $tipoAlerta = 'STOCK_MINIMO';
                $nivel = 'ADVERTENCIA';
            }

            if ($tipoAlerta === null) {
                $this->resolverAlertasAbiertas(
                    $inventarioActual,
                    $usuario,
                    'El stock volvió a estar por encima del mínimo.'
                );

                return null;
            }

            $this->resolverAlertasAbiertasDeOtroTipo(
                $inventarioActual,
                $tipoAlerta,
                $usuario
            );

            $mensaje = $this->crearMensaje(
                $inventarioActual,
                $tipoAlerta,
                $stockActual,
                $stockMinimo
            );

            $alerta = AlertaStock::query()
                ->where('inventario_id', $inventarioActual->id)
                ->where('tipo_alerta', $tipoAlerta)
                ->whereIn('estado', ['ACTIVA', 'ATENDIDA'])
                ->lockForUpdate()
                ->first();

            if ($alerta) {
                $alerta->update([
                    'nivel' => $nivel,
                    'stock_actual' => $stockActual,
                    'stock_minimo' => $stockMinimo,
                    'mensaje' => $mensaje,
                ]);
            } else {
                $alerta = AlertaStock::create([
                    'inventario_id' => $inventarioActual->id,
                    'producto_id' => $inventarioActual->producto_id,
                    'repisa_id' => $inventarioActual->repisa_id,
                    'tipo_alerta' => $tipoAlerta,
                    'nivel' => $nivel,
                    'stock_actual' => $stockActual,
                    'stock_minimo' => $stockMinimo,
                    'mensaje' => $mensaje,
                    'estado' => 'ACTIVA',
                    'detectada_en' => now(),
                ]);
            }

            return $alerta->load([
                'inventario',
                'producto',
                'repisa',
                'atendidaPor',
                'resueltaPor',
            ]);
        });
    }

    public function evaluarTodos(?User $usuario = null): int
    {
        $procesados = 0;

        Inventario::query()
            ->orderBy('id')
            ->chunkById(100, function ($inventarios) use (
                &$procesados,
                $usuario
            ) {
                foreach ($inventarios as $inventario) {
                    $this->evaluarInventario($inventario, $usuario);
                    $procesados++;
                }
            });

        return $procesados;
    }

    private function resolverAlertasAbiertas(
        Inventario $inventario,
        ?User $usuario,
        string $observacion
    ): void {
        AlertaStock::query()
            ->where('inventario_id', $inventario->id)
            ->whereIn('estado', ['ACTIVA', 'ATENDIDA'])
            ->update([
                'estado' => 'RESUELTA',
                'stock_actual' => $inventario->stock_actual,
                'stock_minimo' => $inventario->stock_minimo,
                'mensaje' => 'La condición de stock fue resuelta.',
                'resuelta_por' => $usuario?->id,
                'resuelta_en' => now(),
                'observacion_resolucion' =>
                    'El stock volvió a estar por encima del mínimo.',
            ]);
    }

    private function resolverAlertasAbiertasDeOtroTipo(
        Inventario $inventario,
        string $tipoActual,
        ?User $usuario
    ): void {
        AlertaStock::query()
            ->where('inventario_id', $inventario->id)
            ->where('tipo_alerta', '!=', $tipoActual)
            ->whereIn('estado', ['ACTIVA', 'ATENDIDA'])
            ->update([
                'estado' => 'RESUELTA',
                'stock_actual' => $inventario->stock_actual,
                'stock_minimo' => $inventario->stock_minimo,
                'resuelta_por' => $usuario?->id,
                'resuelta_en' => now(),
                'observacion_resolucion' =>
                    'La condición de stock cambió a otra categoría.',
            ]);
    }

    private function crearMensaje(
        Inventario $inventario,
        string $tipoAlerta,
        float $stockActual,
        float $stockMinimo
    ): string {
        $producto = $inventario->producto?->codigo
            ?? "Producto {$inventario->producto_id}";

        $repisa = $inventario->repisa?->codigo
            ?? "Repisa {$inventario->repisa_id}";

        if ($tipoAlerta === 'SIN_STOCK') {
            return "El producto {$producto} no tiene stock en la repisa {$repisa}.";
        }

        return "El producto {$producto} tiene stock {$stockActual} en la "
            . "repisa {$repisa}; el mínimo configurado es {$stockMinimo}.";
    }
}
