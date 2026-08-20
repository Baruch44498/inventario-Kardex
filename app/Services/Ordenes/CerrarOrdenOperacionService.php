<?php

namespace App\Services\Ordenes;

use App\Models\OrdenOperacion;
use App\Models\User;
use App\Services\Inventario\ReservaMaterialService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CerrarOrdenOperacionService
{
    public function __construct(
        private readonly ReservaMaterialService $reservas,
        private readonly ResumenEjecucionOrdenService $resumenEjecucion
    ) {}

    public function cerrar(
        OrdenOperacion $orden,
        ?string $observacion,
        User $usuario
    ): array {
        return DB::transaction(function () use ($orden, $observacion, $usuario): array {
            $orden = OrdenOperacion::query()
                ->with([
                    'tipoOrden',
                    'cotizacionCliente.detalles',
                    'cotizacionOrigen.detalles',
                    'cotizacionComponente',
                ])
                ->lockForUpdate()
                ->findOrFail($orden->id);

            if (! $orden->estaEnProceso()) {
                throw ValidationException::withMessages([
                    'cierre' => 'Solo una orden en proceso puede cerrarse.',
                ]);
            }

            $ultimoAvance = $orden->avances()
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $ultimoAvance || (float) $ultimoAvance->porcentaje < 99.999) {
                throw ValidationException::withMessages([
                    'cierre' => 'Registra el avance operativo en 100% antes de cerrar la orden.',
                ]);
            }

            if ($this->cantidadHerramientasPendientes($orden->id) > 0) {
                throw ValidationException::withMessages([
                    'cierre' => 'No se puede cerrar mientras existan herramientas pendientes de devolución.',
                ]);
            }

            if (
                $orden->cotizacionVinculada()?->moneda === 'USD'
                && (float) ($orden->cotizacionVinculada()?->tipo_cambio) <= 0
            ) {
                throw ValidationException::withMessages([
                    'cierre' => 'La cotización en dólares no tiene un tipo de cambio válido para congelar la rentabilidad.',
                ]);
            }

            $resumen = $this->resumenEjecucion->construir($orden, true);
            $costos = $resumen['costos'];
            $rentabilidad = $costos['rentabilidad'];
            $reservasLiberadas = $this->reservas->liberarPendientesOrden($orden, $usuario);

            $orden->update([
                'estado' => 'CERRADA',
                'cerrado_en' => now(),
                'cerrado_por' => $usuario->id,
                'observacion_cierre' => $observacion,
                'ingreso_neto_cierre_soles' => $rentabilidad['ingreso_neto_soles'],
                'costo_real_cierre_soles' => $costos['total_real'],
                'utilidad_real_cierre_soles' => $rentabilidad['utilidad_real_soles'],
                'margen_real_cierre_porcentaje' => $rentabilidad['margen_real_porcentaje'],
            ]);

            return [
                'orden' => $orden->fresh(),
                'reservas_liberadas' => $reservasLiberadas,
            ];
        });
    }

    private function cantidadHerramientasPendientes(int $ordenId): int
    {
        $retornos = DB::table('nota_ingreso_detalles as d')
            ->join('notas_ingreso as n', 'n.id', '=', 'd.nota_ingreso_id')
            ->where('n.estado', 'CONFIRMADA')
            ->whereNotNull('d.nota_salida_detalle_id')
            ->groupBy('d.nota_salida_detalle_id')
            ->selectRaw('d.nota_salida_detalle_id, SUM(d.cantidad) as retornado');

        return DB::table('nota_salida_detalles as d')
            ->join('notas_salida as n', 'n.id', '=', 'd.nota_salida_id')
            ->leftJoinSub($retornos, 'ret', fn($join) => $join
                ->on('ret.nota_salida_detalle_id', '=', 'd.id'))
            ->where('n.estado', 'CONFIRMADA')
            ->where('n.orden_operacion_id', $ordenId)
            ->where('d.tratamiento', 'USO_TEMPORAL')
            ->whereRaw('(d.cantidad - COALESCE(ret.retornado, 0)) > 0.0001')
            ->count();
    }
}
