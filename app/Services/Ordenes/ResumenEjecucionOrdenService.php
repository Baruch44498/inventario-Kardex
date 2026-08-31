<?php

namespace App\Services\Ordenes;

use App\Models\OrdenOperacion;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResumenEjecucionOrdenService
{
    public function __construct(private AreasTrabajoOrdenService $areasTrabajo) {}

    public function construir(OrdenOperacion $orden, bool $incluirCostos): array
    {
        $orden->loadMissing([
            'cotizacionCliente.detalles',
            'cotizacionOrigen.detalles',
            'cotizacionComponente',
        ]);

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
            'comparacion_materiales' => $this->comparacionMateriales($orden),
        ];

        if (! $incluirCostos) {
            return $resumen;
        }

        $costoSalidas = round((float) $salidas->sum('costo'), 4);
        $costoRetornos = round((float) $retornos->sum('costo'), 4);
        $costoReal = max(0, round($costoSalidas - $costoRetornos, 4));

        $cotizacion = $orden->cotizacionVinculada();
        $componente = $orden->cotizacionComponente;
        $detallesCotizacion = $cotizacion?->detalles ?? collect();
        if ($componente) {
            $detallesCotizacion = $detallesCotizacion
                ->where('componente_id', $componente->id);
        }
        $factorMoneda = $cotizacion?->moneda === 'USD'
            ? (float) $cotizacion->tipo_cambio
            : 1.0;
        $costoPrevisto = $cotizacion
            ? round((float) $detallesCotizacion->sum(
                fn($detalle): float => (float) $detalle->cantidad
                    * (float) $detalle->costo_referencia
                    * $factorMoneda
            ), 4)
            : 0.0;
        if ($orden->materialesPlanificadosPorArea()->exists()) {
            $costoPrevisto = round((float) $orden
                ->materialesPlanificadosPorArea()
                ->sum('costo_total_estimado_soles'), 4);
        }

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
            ? round((float) ($componente
                ? $detallesCotizacion->sum('subtotal')
                : $cotizacion->subtotal) * $factorMoneda, 4)
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

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function comparacionMateriales(OrdenOperacion $orden): Collection
    {
        $filas = collect();

        foreach ($this->areasTrabajo->areas($orden) as $area) {
            foreach ($this->areasTrabajo->materialesPlanificados($orden, $area) as $productoId => $cantidad) {
                $clave = $area . '|' . (int) $productoId;
                $filas->put($clave, [
                    'area' => $area,
                    'producto_id' => (int) $productoId,
                    'estimado' => round((float) $cantidad, 3),
                    'salida_bruta' => 0.0,
                    'retorno_utilizable' => 0.0,
                    'malogrado' => 0.0,
                ]);
            }
        }

        $salidas = DB::table('nota_salida_detalles as d')
            ->join('notas_salida as n', 'n.id', '=', 'd.nota_salida_id')
            ->where('n.orden_operacion_id', $orden->id)
            ->where('n.estado', 'CONFIRMADA')
            ->where('d.tratamiento', 'CONSUMO')
            ->groupBy('n.area_trabajo', 'd.producto_id')
            ->selectRaw("COALESCE(n.area_trabajo, 'GENERAL') as area")
            ->selectRaw('d.producto_id, SUM(d.cantidad) as cantidad')
            ->get();

        foreach ($salidas as $salida) {
            $area = $this->areasTrabajo->normalizar($salida->area);
            $clave = $area . '|' . (int) $salida->producto_id;
            $fila = $filas->get($clave, [
                'area' => $area,
                'producto_id' => (int) $salida->producto_id,
                'estimado' => 0.0,
                'salida_bruta' => 0.0,
                'retorno_utilizable' => 0.0,
                'malogrado' => 0.0,
            ]);
            $fila['salida_bruta'] = round((float) $salida->cantidad, 3);
            $filas->put($clave, $fila);
        }

        $retornos = DB::table('nota_ingreso_detalles as d')
            ->join('notas_ingreso as i', 'i.id', '=', 'd.nota_ingreso_id')
            ->join('nota_salida_detalles as sd', 'sd.id', '=', 'd.nota_salida_detalle_id')
            ->join('notas_salida as s', 's.id', '=', 'sd.nota_salida_id')
            ->where('s.orden_operacion_id', $orden->id)
            ->where('i.estado', 'CONFIRMADA')
            ->whereIn('i.motivo_ingreso', ['RETORNO_MATERIAL', 'DEVOLUCION_MATERIAL_MALOGRADO'])
            ->groupBy('i.motivo_ingreso', 'i.area_trabajo', 's.area_trabajo', 'd.producto_id')
            ->selectRaw("COALESCE(i.area_trabajo, s.area_trabajo, 'GENERAL') as area")
            ->selectRaw('i.motivo_ingreso, d.producto_id, SUM(d.cantidad) as cantidad')
            ->get();

        foreach ($retornos as $retorno) {
            $area = $this->areasTrabajo->normalizar($retorno->area);
            $clave = $area . '|' . (int) $retorno->producto_id;
            $fila = $filas->get($clave);
            if (! $fila) {
                continue;
            }
            $campo = $retorno->motivo_ingreso === 'RETORNO_MATERIAL'
                ? 'retorno_utilizable'
                : 'malogrado';
            $fila[$campo] = round((float) $retorno->cantidad, 3);
            $filas->put($clave, $fila);
        }

        $productos = Producto::query()
            ->with('unidadMedida')
            ->whereIn('id', $filas->pluck('producto_id')->unique())
            ->get()
            ->keyBy('id');

        return $filas->map(function (array $fila) use ($productos): array {
            $fila['producto'] = $productos->get($fila['producto_id']);
            $fila['real'] = max(0, round($fila['salida_bruta'] - $fila['retorno_utilizable'], 3));
            $fila['diferencia'] = round($fila['real'] - $fila['estimado'], 3);

            return $fila;
        })->sortBy(fn(array $fila): string => $fila['area'] . '|' . ($fila['producto']?->codigo ?? ''))
            ->values();
    }
}
