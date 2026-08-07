<?php

namespace App\Services\Inventario;

use App\Models\OrdenOperacion;
use App\Models\ReservaMaterialOrden;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservaMaterialService
{
    public function agregar(
        OrdenOperacion $orden,
        int $productoId,
        float $cantidad,
        ?string $observacion,
        User $usuario
    ): ReservaMaterialOrden {
        $orden->loadMissing('tipoOrden');

        if (! in_array($orden->tipoOrden?->codigo, ['OM', 'OS', 'OP'], true)) {
            throw ValidationException::withMessages([
                'orden_operacion_id' => 'Solo las órdenes OM, OS y OP admiten reserva de materiales.',
            ]);
        }

        if (in_array($orden->estado, ['CERRADA', 'ANULADA'], true)) {
            throw ValidationException::withMessages([
                'orden_operacion_id' => 'La orden cerrada o anulada no admite nuevas reservas.',
            ]);
        }

        $cantidad = round($cantidad, 3);
        if ($cantidad <= 0) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad a reservar debe ser mayor que cero.',
            ]);
        }

        return DB::transaction(function () use (
            $orden,
            $productoId,
            $cantidad,
            $observacion,
            $usuario
        ): ReservaMaterialOrden {
            $reserva = ReservaMaterialOrden::query()
                ->where('orden_operacion_id', $orden->id)
                ->where('producto_id', $productoId)
                ->lockForUpdate()
                ->first();

            if (! $reserva) {
                return ReservaMaterialOrden::create([
                    'orden_operacion_id' => $orden->id,
                    'producto_id' => $productoId,
                    'cantidad_reservada' => $cantidad,
                    'cantidad_atendida' => 0,
                    'cantidad_liberada' => 0,
                    'estado' => 'ACTIVA',
                    'observacion' => $observacion,
                    'reservado_por' => $usuario->id,
                    'actualizado_por' => $usuario->id,
                ]);
            }

            $reserva->update([
                'cantidad_reservada' => round((float) $reserva->cantidad_reservada + $cantidad, 3),
                'estado' => 'ACTIVA',
                'observacion' => $observacion ?: $reserva->observacion,
                'actualizado_por' => $usuario->id,
            ]);

            return $reserva->fresh();
        });
    }

    /** @return array{reserva_id: ?int, aplicada: float} */
    public function aplicarSalida(
        OrdenOperacion $orden,
        int $productoId,
        float $cantidad,
        User $usuario
    ): array {
        $reserva = ReservaMaterialOrden::query()
            ->where('orden_operacion_id', $orden->id)
            ->where('producto_id', $productoId)
            ->lockForUpdate()
            ->first();

        if (! $reserva || ! $reserva->estaActiva()) {
            return ['reserva_id' => null, 'aplicada' => 0.0];
        }

        $aplicada = min(round($cantidad, 3), $reserva->cantidadPendiente());
        if ($aplicada <= 0) {
            return ['reserva_id' => $reserva->id, 'aplicada' => 0.0];
        }

        $reserva->cantidad_atendida = round((float) $reserva->cantidad_atendida + $aplicada, 3);
        $reserva->actualizado_por = $usuario->id;
        $this->actualizarEstado($reserva);
        $reserva->save();

        return ['reserva_id' => $reserva->id, 'aplicada' => $aplicada];
    }

    public function revertirSalida(int $reservaId, float $cantidad, User $usuario): void
    {
        if ($cantidad <= 0) {
            return;
        }

        $reserva = ReservaMaterialOrden::query()
            ->lockForUpdate()
            ->find($reservaId);

        if (! $reserva) {
            return;
        }

        $cantidadRevertida = min(
            round($cantidad, 3),
            (float) $reserva->cantidad_atendida
        );
        $reserva->cantidad_atendida = max(
            0,
            round((float) $reserva->cantidad_atendida - $cantidadRevertida, 3)
        );

        $reserva->loadMissing('ordenOperacion');
        if (in_array($reserva->ordenOperacion?->estado, ['CERRADA', 'ANULADA'], true)) {
            $saldoLiberable = max(
                0,
                round(
                    (float) $reserva->cantidad_reservada
                    - (float) $reserva->cantidad_atendida
                    - (float) $reserva->cantidad_liberada,
                    3
                )
            );
            $reserva->cantidad_liberada = round(
                (float) $reserva->cantidad_liberada + min($cantidadRevertida, $saldoLiberable),
                3
            );
        }

        $reserva->actualizado_por = $usuario->id;
        $this->actualizarEstado($reserva);
        $reserva->save();
    }

    public function liberar(ReservaMaterialOrden $reserva, User $usuario): ReservaMaterialOrden
    {
        return DB::transaction(function () use ($reserva, $usuario): ReservaMaterialOrden {
            $actual = ReservaMaterialOrden::query()->lockForUpdate()->findOrFail($reserva->id);
            $pendiente = $actual->cantidadPendiente();

            if ($pendiente > 0) {
                $actual->cantidad_liberada = round((float) $actual->cantidad_liberada + $pendiente, 3);
            }
            $actual->actualizado_por = $usuario->id;
            $this->actualizarEstado($actual);
            $actual->save();

            return $actual->fresh();
        });
    }

    public function liberarPendientesOrden(OrdenOperacion $orden, User $usuario): int
    {
        $liberadas = 0;

        ReservaMaterialOrden::query()
            ->where('orden_operacion_id', $orden->id)
            ->where('estado', 'ACTIVA')
            ->orderBy('id')
            ->get()
            ->each(function (ReservaMaterialOrden $reserva) use ($usuario, &$liberadas): void {
                if ($reserva->cantidadPendiente() > 0.0001) {
                    $this->liberar($reserva, $usuario);
                    $liberadas++;
                }
            });

        return $liberadas;
    }

    private function actualizarEstado(ReservaMaterialOrden $reserva): void
    {
        if ($reserva->cantidadPendiente() > 0.0001) {
            $reserva->estado = 'ACTIVA';
            return;
        }

        $reserva->estado = (float) $reserva->cantidad_liberada > 0
            ? 'LIBERADA'
            : 'ATENDIDA';
    }
}
