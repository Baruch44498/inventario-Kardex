<?php

namespace App\Services\Ordenes;

use App\Models\CostoDirectoOrden;
use App\Models\OrdenOperacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarCostoDirectoOrdenService
{
    public function registrar(
        OrdenOperacion $orden,
        array $datos,
        User $usuario
    ): CostoDirectoOrden {
        return DB::transaction(function () use ($orden, $datos, $usuario): CostoDirectoOrden {
            $orden = OrdenOperacion::query()
                ->with('tipoOrden')
                ->lockForUpdate()
                ->findOrFail($orden->id);

            $this->validarOrdenActiva($orden);

            $cantidad = round((float) $datos['cantidad'], 3);
            $costoUnitario = round((float) $datos['costo_unitario_soles'], 4);
            $total = round($cantidad * $costoUnitario, 4);

            return $orden->costosDirectos()->create([
                'tipo' => $datos['tipo'],
                'fecha_costo' => $datos['fecha_costo'],
                'descripcion' => trim($datos['descripcion']),
                'proveedor_id' => $datos['proveedor_id'] ?? null,
                'cantidad' => $cantidad,
                'unidad' => $datos['unidad'],
                'costo_unitario_soles' => $costoUnitario,
                'total_soles' => $total,
                'documento_referencia' => $datos['documento_referencia'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
                'estado' => 'VIGENTE',
                'registrado_por' => $usuario->id,
                'registrado_en' => now(),
            ]);
        });
    }

    public function anular(
        CostoDirectoOrden $costo,
        string $motivo,
        User $usuario
    ): CostoDirectoOrden {
        return DB::transaction(function () use ($costo, $motivo, $usuario): CostoDirectoOrden {
            $costo = CostoDirectoOrden::query()
                ->with('ordenOperacion.tipoOrden')
                ->lockForUpdate()
                ->findOrFail($costo->id);

            $orden = OrdenOperacion::query()
                ->with('tipoOrden')
                ->lockForUpdate()
                ->findOrFail($costo->orden_operacion_id);

            $this->validarOrdenActiva($orden);

            if (! $costo->estaVigente()) {
                throw ValidationException::withMessages([
                    'motivo_anulacion' => 'Este costo ya se encuentra anulado.',
                ]);
            }

            $costo->update([
                'estado' => 'ANULADO',
                'anulado_por' => $usuario->id,
                'anulado_en' => now(),
                'motivo_anulacion' => trim($motivo),
            ]);

            return $costo->fresh(['proveedor', 'registradoPor', 'anuladoPor']);
        });
    }

    private function validarOrdenActiva(OrdenOperacion $orden): void
    {
        if (! in_array($orden->tipoOrden?->codigo, ['OM', 'OS', 'OP'], true)) {
            throw ValidationException::withMessages([
                'tipo' => 'Solo las órdenes OM, OS y OP admiten costos directos.',
            ]);
        }

        if (! $orden->estaEnProceso()) {
            throw ValidationException::withMessages([
                'tipo' => 'La orden debe estar en proceso para modificar sus costos directos.',
            ]);
        }
    }
}
