<?php

namespace App\Services\Ordenes;

use App\Models\HistorialMaterialRequeridoOrden;
use App\Models\MaterialRequeridoOrden;
use App\Models\OrdenOperacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialRequeridoOrdenService
{
    public function agregar(
        OrdenOperacion $orden,
        int $productoId,
        float $cantidad,
        ?string $motivo,
        User $usuario
    ): MaterialRequeridoOrden {
        $this->validarOrdenEditable($orden);

        $cantidad = round($cantidad, 3);
        if ($cantidad <= 0) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad requerida debe ser mayor que cero.',
            ]);
        }

        return DB::transaction(function () use ($orden, $productoId, $cantidad, $motivo, $usuario): MaterialRequeridoOrden {
            $material = MaterialRequeridoOrden::query()
                ->where('orden_operacion_id', $orden->id)
                ->where('producto_id', $productoId)
                ->lockForUpdate()
                ->first();

            if (! $material) {
                $material = MaterialRequeridoOrden::query()->create([
                    'orden_operacion_id' => $orden->id,
                    'producto_id' => $productoId,
                    'cantidad_requerida' => $cantidad,
                    'observacion' => $motivo,
                    'creado_por' => $usuario->id,
                    'actualizado_por' => $usuario->id,
                ]);

                $this->registrarHistorial(
                    $material,
                    'INICIAL',
                    0,
                    $cantidad,
                    $cantidad,
                    $motivo ?: 'Requerimiento inicial de la orden.',
                    $usuario
                );

                return $material->fresh();
            }

            $anterior = round((float) $material->cantidad_requerida, 3);
            $nueva = round($anterior + $cantidad, 3);

            $material->update([
                'cantidad_requerida' => $nueva,
                'observacion' => $motivo ?: $material->observacion,
                'actualizado_por' => $usuario->id,
            ]);

            $this->registrarHistorial(
                $material,
                'ADICIONAL',
                $anterior,
                $cantidad,
                $nueva,
                $motivo ?: 'Material adicional requerido durante la ejecución.',
                $usuario
            );

            return $material->fresh();
        });
    }

    public function modificar(
        MaterialRequeridoOrden $material,
        float $cantidadNueva,
        float $cantidadEntregada,
        string $motivo,
        User $usuario
    ): MaterialRequeridoOrden {
        $material->loadMissing('ordenOperacion');
        $this->validarOrdenEditable($material->ordenOperacion);

        $cantidadNueva = round($cantidadNueva, 3);
        $cantidadEntregada = round($cantidadEntregada, 3);

        if ($cantidadNueva <= 0) {
            throw ValidationException::withMessages([
                'cantidad_nueva' => 'La cantidad requerida debe ser mayor que cero.',
            ]);
        }

        if ($cantidadNueva + 0.0001 < $cantidadEntregada) {
            throw ValidationException::withMessages([
                'cantidad_nueva' => 'No puedes reducir el requerimiento por debajo de lo ya entregado físicamente ('.number_format($cantidadEntregada, 2).').',
            ]);
        }

        return DB::transaction(function () use ($material, $cantidadNueva, $motivo, $usuario): MaterialRequeridoOrden {
            $actual = MaterialRequeridoOrden::query()->lockForUpdate()->findOrFail($material->id);
            $anterior = round((float) $actual->cantidad_requerida, 3);
            $cambio = round($cantidadNueva - $anterior, 3);

            if (abs($cambio) <= 0.0001) {
                throw ValidationException::withMessages([
                    'cantidad_nueva' => 'La nueva cantidad es igual al requerimiento actual.',
                ]);
            }

            $actual->update([
                'cantidad_requerida' => $cantidadNueva,
                'observacion' => $motivo,
                'actualizado_por' => $usuario->id,
            ]);

            $this->registrarHistorial(
                $actual,
                $cambio > 0 ? 'AJUSTE_AUMENTO' : 'AJUSTE_REDUCCION',
                $anterior,
                $cambio,
                $cantidadNueva,
                $motivo,
                $usuario
            );

            return $actual->fresh();
        });
    }

    private function validarOrdenEditable(OrdenOperacion $orden): void
    {
        $orden->loadMissing('tipoOrden');

        if (! in_array($orden->tipoOrden?->codigo, ['OM', 'OS', 'OP'], true)) {
            throw ValidationException::withMessages([
                'orden_operacion_id' => 'Solo las órdenes OM, OS y OP admiten materiales requeridos.',
            ]);
        }

        if (! in_array($orden->estado, ['ABIERTA', 'EN_PROCESO'], true)) {
            throw ValidationException::withMessages([
                'orden_operacion_id' => 'La orden cerrada o anulada no admite cambios de materiales.',
            ]);
        }
    }

    private function registrarHistorial(
        MaterialRequeridoOrden $material,
        string $tipo,
        float $anterior,
        float $cambio,
        float $nueva,
        ?string $motivo,
        User $usuario
    ): void {
        HistorialMaterialRequeridoOrden::query()->create([
            'material_requerido_orden_id' => $material->id,
            'orden_operacion_id' => $material->orden_operacion_id,
            'producto_id' => $material->producto_id,
            'tipo_movimiento' => $tipo,
            'cantidad_anterior' => round($anterior, 3),
            'cantidad_cambio' => round($cambio, 3),
            'cantidad_nueva' => round($nueva, 3),
            'motivo' => $motivo,
            'registrado_por' => $usuario->id,
            'created_at' => now(),
        ]);
    }
}
