<?php

namespace App\Services\Compras;

use App\Models\OrdenCompra;
use App\Models\Requisicion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnularOrdenCompraService
{
    public function __construct(
        private CompararCotizacionesRequerimientoService $comparador,
        private HistorialRequerimientoCompraService $historial
    ) {}

    /** @return array{orden: OrdenCompra, requerimiento_reabierto: Requisicion|null} */
    public function ejecutar(
        OrdenCompra $ordenCompra,
        User $usuario,
        string $motivo
    ): array {
        return DB::transaction(function () use ($ordenCompra, $usuario, $motivo): array {
            $orden = OrdenCompra::query()
                ->with('solicitudCompra.cotizacion')
                ->lockForUpdate()
                ->findOrFail($ordenCompra->id);

            if (! $orden->puedeAnularse()) {
                throw ValidationException::withMessages([
                    'orden' => 'No puede anularse una orden con recepción, factura o un estado posterior.',
                ]);
            }

            $orden->update([
                'estado' => 'ANULADA',
                'anulado_por' => $usuario->id,
                'anulado_en' => now(),
                'motivo_anulacion' => $motivo,
            ]);

            $requerimientoId = $orden->solicitudCompra?->cotizacion?->requisicion_id;
            if (! $requerimientoId) {
                return [
                    'orden' => $orden->refresh(),
                    'requerimiento_reabierto' => null,
                ];
            }

            $requerimiento = Requisicion::query()
                ->lockForUpdate()
                ->find($requerimientoId);

            if (
                ! $requerimiento
                || $requerimiento->estaAnulada()
                || $requerimiento->estado !== 'ATENDIDA'
                || ! $this->tieneLineasSinCompraVigente($requerimiento)
            ) {
                return [
                    'orden' => $orden->refresh(),
                    'requerimiento_reabierto' => null,
                ];
            }

            $requerimiento = $this->historial->cambiarEstado(
                $requerimiento,
                ['ATENDIDA'],
                'COTIZANDO',
                $usuario,
                "La orden {$orden->codigo} fue anulada; las líneas sin compra vigente regresaron a cotización.",
                [
                    'atendido_por' => null,
                    'atendido_en' => null,
                ]
            );

            return [
                'orden' => $orden->refresh(),
                'requerimiento_reabierto' => $requerimiento,
            ];
        });
    }

    private function tieneLineasSinCompraVigente(Requisicion $requerimiento): bool
    {
        $lineaIds = $requerimiento->detalles()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        if ($lineaIds->isEmpty()) {
            return false;
        }

        $compras = $this->comparador->comprasPorLinea($lineaIds);

        return $lineaIds->contains(
            fn (int $lineaId): bool => ! $compras->has($lineaId)
        );
    }
}
