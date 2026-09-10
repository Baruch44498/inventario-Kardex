<?php

namespace App\Services\Compras;

use App\Models\AlertaStock;
use App\Models\Requisicion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VincularAlertasRequerimientoService
{
    /** @param array<int, int|string> $alertaIds */
    public function vincular(Requisicion $requerimiento, array $alertaIds): void
    {
        $ids = collect($alertaIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            $requerimiento->alertasStock()->sync([]);

            return;
        }

        $alertas = AlertaStock::query()
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get();

        if ($alertas->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'alerta_ids' => 'Una de las alertas seleccionadas ya no está disponible.',
            ]);
        }

        if ($alertas->contains(fn (AlertaStock $alerta): bool => ! $alerta->estaActiva())) {
            throw ValidationException::withMessages([
                'alerta_ids' => 'Una de las alertas seleccionadas ya fue resuelta y no puede originar otra reposición.',
            ]);
        }

        $productosRequerimiento = $requerimiento->detalles()
            ->pluck('producto_id')
            ->map(fn ($id): int => (int) $id)
            ->unique();
        $productoAjeno = $alertas->first(
            fn (AlertaStock $alerta): bool => ! $productosRequerimiento->contains((int) $alerta->producto_id)
        );

        if ($productoAjeno) {
            throw ValidationException::withMessages([
                'alerta_ids' => 'Las alertas deben corresponder a los productos incluidos en el requerimiento.',
            ]);
        }

        $conflicto = DB::table('alerta_stock_requisicion as ar')
            ->join('requisiciones as r', 'r.id', '=', 'ar.requisicion_id')
            ->whereIn('ar.alerta_stock_id', $ids)
            ->where('ar.requisicion_id', '!=', $requerimiento->id)
            ->where('r.estado', '!=', 'ANULADA')
            ->first(['r.codigo']);

        if ($conflicto) {
            throw ValidationException::withMessages([
                'alerta_ids' => "Una alerta ya se encuentra vinculada al requerimiento {$conflicto->codigo}.",
            ]);
        }

        $requerimiento->alertasStock()->sync($ids->all());
    }

    public function marcarEnGestion(Requisicion $requerimiento, User $usuario): void
    {
        $alertaIds = $requerimiento->alertasStock()->pluck('alertas_stock.id');

        if ($alertaIds->isEmpty()) {
            return;
        }

        AlertaStock::query()
            ->whereIn('id', $alertaIds)
            ->whereIn('estado', ['ACTIVA', 'ATENDIDA'])
            ->update([
                'estado' => 'ATENDIDA',
                'atendida_por' => $usuario->id,
                'atendida_en' => now(),
            ]);
    }
}
