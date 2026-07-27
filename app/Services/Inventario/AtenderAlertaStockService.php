<?php

namespace App\Services\Inventario;

use App\Models\AlertaStock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AtenderAlertaStockService
{
    public function atender(
        AlertaStock $alertaStock,
        User $usuario
    ): AlertaStock {
        return DB::transaction(function () use ($alertaStock, $usuario) {
            $alerta = AlertaStock::query()
                ->lockForUpdate()
                ->findOrFail($alertaStock->id);

            if ($alerta->estado !== 'ACTIVA') {
                throw ValidationException::withMessages([
                    'estado' =>
                        'Solo se puede atender una alerta que esté activa.',
                ]);
            }

            $alerta->update([
                'estado' => 'ATENDIDA',
                'atendida_por' => $usuario->id,
                'atendida_en' => now(),
            ]);

            return $alerta->fresh([
                'inventario',
                'producto',
                'repisa',
                'atendidaPor',
            ]);
        });
    }
}
