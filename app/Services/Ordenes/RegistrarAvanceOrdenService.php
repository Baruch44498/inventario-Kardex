<?php

namespace App\Services\Ordenes;

use App\Models\AvanceOrdenOperacion;
use App\Models\OrdenOperacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarAvanceOrdenService
{
    public function registrar(
        OrdenOperacion $orden,
        array $datos,
        User $usuario
    ): AvanceOrdenOperacion {
        return DB::transaction(function () use ($orden, $datos, $usuario): AvanceOrdenOperacion {
            $orden = OrdenOperacion::query()
                ->with('tipoOrden')
                ->lockForUpdate()
                ->findOrFail($orden->id);

            if (! in_array($orden->tipoOrden?->codigo, ['OM', 'OS', 'OP'], true)) {
                throw ValidationException::withMessages([
                    'porcentaje' => 'Solo las órdenes OM, OS y OP registran avance operativo.',
                ]);
            }

            if (! $orden->estaEnProceso()) {
                throw ValidationException::withMessages([
                    'porcentaje' => 'La orden debe estar en proceso para registrar avances.',
                ]);
            }

            $porcentaje = round((float) $datos['porcentaje'], 2);
            $ultimo = $orden->avances()
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($ultimo && $porcentaje + 0.001 < (float) $ultimo->porcentaje) {
                throw ValidationException::withMessages([
                    'porcentaje' => 'El nuevo avance no puede ser menor que el último registrado.',
                ]);
            }

            return $orden->avances()->create([
                'porcentaje' => $porcentaje,
                'detalle' => trim($datos['detalle']),
                'registrado_por' => $usuario->id,
                'registrado_en' => now(),
            ]);
        });
    }
}
