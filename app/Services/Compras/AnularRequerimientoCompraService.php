<?php

namespace App\Services\Compras;

use App\Models\Cotizacion;
use App\Models\Requisicion;
use App\Models\SolicitudCompra;
use App\Models\User;
use App\Services\Inventario\EvaluarAlertasStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnularRequerimientoCompraService
{
    public function __construct(
        private HistorialRequerimientoCompraService $historial,
        private EvaluarAlertasStockService $evaluarAlertas
    ) {}

    public function ejecutar(
        Requisicion $requerimiento,
        User $usuario,
        string $motivo
    ): Requisicion
    {
        return DB::transaction(function () use ($requerimiento, $usuario, $motivo): Requisicion {
            $actual = Requisicion::query()
                ->with('alertasStock.inventario')
                ->lockForUpdate()
                ->findOrFail($requerimiento->id);

            if ($actual->estaAnulada()) {
                throw ValidationException::withMessages([
                    'estado' => 'El requerimiento ya se encuentra anulado.',
                ]);
            }

            if ($this->tieneOrdenCompraActiva($actual)) {
                throw ValidationException::withMessages([
                    'estado' => 'No se puede anular porque ya generó una orden de compra vigente. Anula primero las órdenes relacionadas, siempre que aún no tengan recepciones.',
                ]);
            }

            $actual = $this->historial->cambiarEstado(
                $actual,
                ['BORRADOR', 'ENVIADA', 'EN_REVISION', 'COTIZANDO', 'ATENDIDA'],
                'ANULADA',
                $usuario,
                'Requerimiento anulado: '.$motivo,
                [
                    'anulado_por' => $usuario->id,
                    'anulado_en' => now(),
                    'motivo_anulacion' => $motivo,
                    'estado_abastecimiento' => 'PENDIENTE',
                    'abastecido_en' => null,
                ]
            );

            $this->invalidarDocumentosPendientes($actual, $usuario, $motivo);
            $actual->load('alertasStock.inventario');
            $this->liberarAlertas($actual, $usuario);

            return $actual->fresh(['alertasStock']);
        });
    }

    public function tieneOrdenCompraActiva(Requisicion $requerimiento): bool
    {
        return DB::table('ordenes_compra as oc')
            ->join('solicitudes_compra as sc', 'sc.id', '=', 'oc.solicitud_compra_id')
            ->join('cotizaciones as c', 'c.id', '=', 'sc.cotizacion_id')
            ->where('c.requisicion_id', $requerimiento->id)
            ->where('oc.estado', '!=', 'ANULADA')
            ->exists();
    }

    private function invalidarDocumentosPendientes(
        Requisicion $requerimiento,
        User $usuario,
        string $motivo
    ): void {
        $motivoRelacionado = mb_substr('Requerimiento anulado: '.$motivo, 0, 500);
        $cotizacionIds = Cotizacion::query()
            ->where('requisicion_id', $requerimiento->id)
            ->pluck('id');

        if ($cotizacionIds->isEmpty()) {
            return;
        }

        SolicitudCompra::query()
            ->whereIn('cotizacion_id', $cotizacionIds)
            ->where('estado', '!=', 'ANULADA')
            ->update([
                'estado' => 'ANULADA',
                'anulado_por' => $usuario->id,
                'anulado_en' => now(),
                'motivo_anulacion' => $motivoRelacionado,
            ]);

        Cotizacion::query()
            ->whereIn('id', $cotizacionIds)
            ->whereIn('estado', ['REGISTRADA', 'SELECCIONADA'])
            ->update([
                'estado' => 'ANULADA',
                'anulado_por' => $usuario->id,
                'anulado_en' => now(),
                'motivo_anulacion' => $motivoRelacionado,
            ]);
    }

    private function liberarAlertas(Requisicion $requerimiento, User $usuario): void
    {
        foreach ($requerimiento->alertasStock as $alerta) {
            $tieneOtroRequerimiento = DB::table('alerta_stock_requisicion as ar')
                ->join('requisiciones as r', 'r.id', '=', 'ar.requisicion_id')
                ->where('ar.alerta_stock_id', $alerta->id)
                ->where('ar.requisicion_id', '!=', $requerimiento->id)
                ->where('r.estado', '!=', 'ANULADA')
                ->exists();

            if ($tieneOtroRequerimiento || $alerta->estado === 'RESUELTA') {
                continue;
            }

            if ($alerta->estado === 'ATENDIDA') {
                $alerta->update([
                    'estado' => 'ACTIVA',
                    'atendida_por' => null,
                    'atendida_en' => null,
                ]);
            }

            $inventario = $alerta->inventario?->refresh();
            if ($inventario) {
                $this->evaluarAlertas->evaluarInventario($inventario, $usuario);
            }
        }
    }
}
