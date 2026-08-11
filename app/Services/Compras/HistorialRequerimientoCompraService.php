<?php

namespace App\Services\Compras;

use App\Models\Requisicion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HistorialRequerimientoCompraService
{
    public function registrarInicial(
        Requisicion $requerimiento,
        User $usuario,
        ?string $observacion = null
    ): void {
        $requerimiento->historial()->create([
            'estado_anterior' => null,
            'estado_nuevo' => $requerimiento->estado,
            'observacion' => $this->limpiarObservacion($observacion),
            'registrado_por' => $usuario->id,
            'created_at' => now(),
        ]);
    }

    /**
     * @param array<int, string> $estadosPermitidos
     * @param array<string, mixed> $camposAdicionales
     */
    public function cambiarEstado(
        Requisicion $requerimiento,
        array $estadosPermitidos,
        string $nuevoEstado,
        User $usuario,
        ?string $observacion = null,
        array $camposAdicionales = []
    ): Requisicion {
        return DB::transaction(function () use (
            $requerimiento,
            $estadosPermitidos,
            $nuevoEstado,
            $usuario,
            $observacion,
            $camposAdicionales
        ): Requisicion {
            /** @var Requisicion $actual */
            $actual = Requisicion::query()
                ->lockForUpdate()
                ->findOrFail($requerimiento->id);

            if (! in_array($actual->estado, $estadosPermitidos, true)) {
                throw ValidationException::withMessages([
                    'estado' => "El requerimiento no puede pasar de {$actual->estado} a {$nuevoEstado}.",
                ]);
            }

            $estadoAnterior = $actual->estado;

            $actual->update([
                ...$camposAdicionales,
                'estado' => $nuevoEstado,
            ]);

            $actual->historial()->create([
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $nuevoEstado,
                'observacion' => $this->limpiarObservacion($observacion),
                'registrado_por' => $usuario->id,
                'created_at' => now(),
            ]);

            return $actual->refresh();
        });
    }

    private function limpiarObservacion(?string $observacion): ?string
    {
        $observacion = trim((string) $observacion);

        return $observacion !== '' ? $observacion : null;
    }
}
