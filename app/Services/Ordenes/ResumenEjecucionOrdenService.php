<?php

namespace App\Services\Ordenes;

use App\Models\OrdenOperacion;
use Illuminate\Support\Facades\DB;

class ResumenEjecucionOrdenService
{
    public function construir(OrdenOperacion $orden, bool $incluirCostos): array
    {
        $orden->loadMissing('cotizacionCliente.detalles');

        $salidas = DB::table('nota_salida_detalles as d')
            ->join('notas_salida as n', 'n.id', '=', 'd.nota_salida_id')
            ->where('n.orden_operacion_id', $orden->id)
            ->where('n.estado', 'CONFIRMADA')
            ->where('d.tratamiento', 'CONSUMO')
            ->groupBy('d.producto_id')
            ->selectRaw('d.producto_id')
            ->selectRaw('SUM(d.cantidad) as cantidad')
            ->selectRaw('SUM(d.subtotal) as costo')
            ->get()
            ->keyBy('producto_id');

        $retornos = DB::table('nota_ingreso_detalles as d')
            ->join('notas_ingreso as i', 'i.id', '=', 'd.nota_ingreso_id')
            ->join('nota_salida_detalles as sd', 'sd.id', '=', 'd.nota_salida_detalle_id')
            ->join('notas_salida as s', 's.id', '=', 'sd.nota_salida_id')
            ->where('s.orden_operacion_id', $orden->id)
            ->where('s.estado', 'CONFIRMADA')
            ->where('i.estado', 'CONFIRMADA')
            ->where('i.motivo_ingreso', 'RETORNO_MATERIAL')
            ->where('sd.tratamiento', 'CONSUMO')
            ->groupBy('sd.producto_id')
            ->selectRaw('sd.producto_id')
            ->selectRaw('SUM(d.cantidad) as cantidad')
            ->selectRaw('SUM(d.subtotal) as costo')
            ->get()
            ->keyBy('producto_id');

        $materiales = $orden->materialesRequeridos()
            ->orderBy('id')
            ->get();
        $sumaPorcentajes = 0.0;
        $lineasAtendidas = 0;

        foreach ($materiales as $material) {
            $salida = $salidas->get($material->producto_id);
            $retorno = $retornos->get($material->producto_id);
            $entregadaNeta = max(0, round(
                (float) ($salida->cantidad ?? 0)
                    - (float) ($retorno->cantidad ?? 0),
                3
            ));
            $requerida = max(0, (float) $material->cantidad_requerida);
            $porcentaje = $requerida > 0.0001
                ? min(100, round(($entregadaNeta / $requerida) * 100, 2))
                : 100.0;

            $sumaPorcentajes += $porcentaje;
            if ($porcentaje >= 99.999) {
                $lineasAtendidas++;
            }
        }

        $ultimoAvance = $orden->avances()
            ->with('registradoPor')
            ->latest('id')
            ->first();
        $avanceOperativo = $orden->estaCerrada()
            ? 100.0
            : (float) ($ultimoAvance?->porcentaje ?? 0);

        $resumen = [
            'avance_operativo' => round($avanceOperativo, 2),
            'avance_materiales' => $materiales->isEmpty()
                ? 0.0
                : round($sumaPorcentajes / $materiales->count(), 2),
            'lineas_materiales' => $materiales->count(),
            'lineas_atendidas' => $lineasAtendidas,
            'ultimo_avance' => $ultimoAvance,
            'costos' => null,
        ];

        if (! $incluirCostos) {
            return $resumen;
        }

        $costoSalidas = round((float) $salidas->sum('costo'), 4);
        $costoRetornos = round((float) $retornos->sum('costo'), 4);
        $costoReal = max(0, round($costoSalidas - $costoRetornos, 4));

        $cotizacion = $orden->cotizacionCliente;
        $factorMoneda = $cotizacion?->moneda === 'USD'
            ? (float) $cotizacion->tipo_cambio
            : 1.0;
        $costoPrevisto = $cotizacion
            ? round((float) $cotizacion->detalles->sum(
                fn($detalle): float => (float) $detalle->cantidad
                    * (float) $detalle->costo_referencia
                    * $factorMoneda
            ), 4)
            : 0.0;

        $costosDirectos = DB::table('costos_directos_orden')
            ->where('orden_operacion_id', $orden->id)
            ->where('estado', 'VIGENTE')
            ->groupBy('tipo')
            ->selectRaw('tipo, SUM(total_soles) as total')
            ->get()
            ->keyBy('tipo');
        $totalCostosDirectos = round((float) $costosDirectos->sum('total'), 4);
        $totalReal = round($costoReal + $totalCostosDirectos, 4);
        $ingresoNeto = $cotizacion
            ? round((float) $cotizacion->subtotal * $factorMoneda, 4)
            : null;
        $utilidadReal = $ingresoNeto !== null
            ? round($ingresoNeto - $totalReal, 4)
            : null;
        $margenReal = $ingresoNeto !== null && $ingresoNeto > 0.0001
            ? round(($utilidadReal / $ingresoNeto) * 100, 4)
            : null;
        $cierreCongelado = $orden->estaCerrada()
            && $orden->costo_real_cierre_soles !== null;

        if ($cierreCongelado) {
            $totalReal = round((float) $orden->costo_real_cierre_soles, 4);
            $ingresoNeto = $orden->ingreso_neto_cierre_soles !== null
                ? round((float) $orden->ingreso_neto_cierre_soles, 4)
                : null;
            $utilidadReal = $orden->utilidad_real_cierre_soles !== null
                ? round((float) $orden->utilidad_real_cierre_soles, 4)
                : null;
            $margenReal = $orden->margen_real_cierre_porcentaje !== null
                ? round((float) $orden->margen_real_cierre_porcentaje, 4)
                : null;
        }

        $resultadoRentabilidad = $utilidadReal === null
            ? 'NO_DISPONIBLE'
            : ($utilidadReal > 0.0001
                ? 'UTILIDAD'
                : ($utilidadReal < -0.0001 ? 'PERDIDA' : 'EQUILIBRIO'));

        $resumen['costos'] = [
            'previsto_materiales' => $costoPrevisto,
            'salidas_consumo' => $costoSalidas,
            'retornos' => $costoRetornos,
            'real_materiales' => $costoReal,
            'desviacion' => round($costoReal - $costoPrevisto, 4),
            'directos' => $totalCostosDirectos,
            'total_real' => $totalReal,
            'directos_por_tipo' => $costosDirectos->map(
                fn($fila): float => round((float) $fila->total, 4)
            )->all(),
            'rentabilidad' => [
                'disponible' => $ingresoNeto !== null,
                'cierre_congelado' => $cierreCongelado,
                'ingreso_neto_soles' => $ingresoNeto,
                'utilidad_real_soles' => $utilidadReal,
                'margen_real_porcentaje' => $margenReal,
                'resultado' => $resultadoRentabilidad,
                'moneda_cotizacion' => $cotizacion?->moneda,
                'subtotal_cotizacion' => $cotizacion !== null
                    ? round((float) $cotizacion->subtotal, 4)
                    : null,
                'tipo_cambio_aplicado' => $cotizacion?->moneda === 'USD'
                    ? round($factorMoneda, 6)
                    : 1.0,
            ],
        ];

        return $resumen;
    }
}
