<?php

namespace App\Services\Ordenes;

use App\Models\CostoDirectoOrden;
use App\Models\CotizacionPresupuesto;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class GastoRealOrdenService
{
    public function construir(OrdenOperacion $raiz): array
    {
        // IDs únicos: una OS nunca se contabiliza dos veces, incluso ante ciclos heredados.
        $ordenes = new \Illuminate\Database\Eloquent\Collection([$raiz->id => $raiz]);
        $pendientes = [$raiz->id];
        while ($pendientes !== []) {
            $hijas = OrdenOperacion::query()->whereIn('orden_padre_id', $pendientes)
                ->where('estado', '!=', 'ANULADA')->whereNotIn('id', $ordenes->keys())->get();
            $pendientes = $hijas->modelKeys();
            foreach ($hijas as $hija) {
                $ordenes->put($hija->id, $hija);
            }
        }
        $ordenes->load(['todasLasAreas', 'materialesPlanificadosPorArea', 'materialesRequeridos', 'presupuestoServicioOrigen']);
        $filas = [];
        $avisos = [];
        $movimientos = [];
        $areaIdentificada = function (OrdenOperacion $orden, ?int $areaId, ?string $nombre): array {
            $normalizado = mb_strtoupper(trim((string) $nombre));
            $area = $areaId ? $orden->todasLasAreas->firstWhere('id', $areaId) : null;
            if (! $area && ! $areaId && $normalizado !== '') {
                $coincidencias = $orden->todasLasAreas->filter(fn($a) => mb_strtoupper(trim($a->nombre)) === $normalizado);
                $area = $coincidencias->count() === 1 ? $coincidencias->first() : null;
            }
            $areaClave = $area ? 'id:' . $area->id : 'texto:' . ($normalizado ?: 'SIN ÁREA REGISTRADA');
            $nombreArea = $area?->nombre ?? ($normalizado ?: 'SIN ÁREA REGISTRADA');
            $padre = $area;
            $vistos = [];
            while ($padre?->area_padre_id && ! in_array($padre->id, $vistos, true)) {
                $vistos[] = $padre->id;
                $padre = $orden->todasLasAreas->firstWhere('id', $padre->area_padre_id);
                if ($padre) {
                    $nombreArea = $padre->nombre . ' / ' . $nombreArea;
                }
            }
            return ['clave' => $orden->id . '|' . $areaClave, 'nombre' => $nombreArea];
        };
        $resolver = function (int $ordenId, ?int $areaId, ?string $nombre, int $productoId) use (&$filas, $ordenes, $areaIdentificada): string {
            $orden = $ordenes->get($ordenId);
            $area = $areaIdentificada($orden, $areaId, $nombre);
            $clave = $area['clave'] . '|' . $productoId;
            $filas[$clave] ??= [
                'orden_id' => $ordenId,
                'orden' => $orden->codigo_orden,
                'area_clave' => $area['clave'],
                'area' => $area['nombre'],
                'producto_id' => $productoId,
                'codigo' => '',
                'producto' => '',
                'unidad' => '',
                'estimado' => 0.0,
                'costo_estimado' => 0.0,
                'planificado' => false,
                'salida' => 0.0,
                'costo_salida' => 0.0,
                'retorno' => 0.0,
                'costo_retorno' => 0.0,
                'malogrado' => 0.0,
                'costo_malogrado' => 0.0,
            ];
            return $clave;
        };
        foreach ($ordenes as $orden) {
            foreach ($orden->materialesPlanificadosPorArea as $plan) {
                $clave = $resolver($orden->id, $plan->orden_area_id, null, $plan->producto_id);
                $filas[$clave]['estimado'] += (float) $plan->cantidad_estimada;
                $filas[$clave]['costo_estimado'] += (float) $plan->costo_total_estimado_soles;
                $filas[$clave]['planificado'] = true;
                $filas[$clave]['codigo'] = $plan->codigo_producto;
                $filas[$clave]['producto'] = $plan->descripcion_producto;
                $filas[$clave]['unidad'] = $plan->unidad;
            }
            if ($orden->materialesPlanificadosPorArea->isEmpty()) {
                foreach ($orden->materialesRequeridos as $plan) {
                    $clave = $resolver($orden->id, null, null, $plan->producto_id);
                    $filas[$clave]['estimado'] += $plan->cantidadInicial();
                    $filas[$clave]['costo_estimado'] = null;
                    $filas[$clave]['planificado'] = true;
                    $avisos[] = $orden->codigo_orden . ': planificación antigua sin costo congelado por área. No se inventa un costo estimado.';
                }
            }
        }
        $salidas = DB::table('nota_salida_detalles as d')->join('notas_salida as n', 'n.id', '=', 'd.nota_salida_id')
            ->whereIn('n.orden_operacion_id', $ordenes->keys())->where('n.estado', 'CONFIRMADA')
            ->where('d.tratamiento', 'CONSUMO')->orderBy('d.id')
            ->select('d.id', 'd.producto_id', 'd.cantidad', 'd.subtotal', 'n.orden_operacion_id', 'n.orden_area_id', 'n.area_trabajo', 'n.codigo', 'n.fecha_salida')->get();
        $clavesSalida = [];
        foreach ($salidas as $salida) {
            $clave = $resolver($salida->orden_operacion_id, $salida->orden_area_id, $salida->area_trabajo, $salida->producto_id);
            $clavesSalida[$salida->id] = $clave;
            $filas[$clave]['salida'] += (float) $salida->cantidad;
            $filas[$clave]['costo_salida'] += (float) $salida->subtotal;
            $movimientos[] = [
                'clave' => $clave,
                'documento' => $salida->codigo,
                'origen' => '',
                'fecha' => $salida->fecha_salida,
                'tipo' => 'SALIDA',
                'cantidad' => (float) $salida->cantidad,
                'importe' => (float) $salida->subtotal
            ];
        }
        $retornos = DB::table('nota_ingreso_detalles as d')->join('notas_ingreso as n', 'n.id', '=', 'd.nota_ingreso_id')
            ->join('nota_salida_detalles as sd', 'sd.id', '=', 'd.nota_salida_detalle_id')
            ->join('notas_salida as s', 's.id', '=', 'sd.nota_salida_id')
            ->whereIn('s.orden_operacion_id', $ordenes->keys())->where('s.estado', 'CONFIRMADA')
            ->where('sd.tratamiento', 'CONSUMO')->where('n.estado', 'CONFIRMADA')
            ->whereIn('n.motivo_ingreso', ['RETORNO_MATERIAL', 'DEVOLUCION_MATERIAL_MALOGRADO'])
            ->orderBy('d.id')->select('d.*', 'n.motivo_ingreso', 'n.codigo', 'n.fecha_ingreso', 's.codigo as salida_codigo')->get();
        foreach ($retornos as $retorno) {
            $clave = $clavesSalida[$retorno->nota_salida_detalle_id];
            $tipo = $retorno->motivo_ingreso === 'RETORNO_MATERIAL' ? 'retorno' : 'malogrado';
            $filas[$clave][$tipo] += (float) $retorno->cantidad;
            $filas[$clave]['costo_' . $tipo] += (float) $retorno->subtotal;
            $movimientos[] = [
                'clave' => $clave,
                'documento' => $retorno->codigo,
                'origen' => $retorno->salida_codigo,
                'fecha' => $retorno->fecha_ingreso,
                'tipo' => strtoupper($tipo),
                'cantidad' => (float) $retorno->cantidad,
                'importe' => (float) $retorno->subtotal
            ];
        }
        $productos = Producto::with('unidadMedida')->whereIn('id', array_column($filas, 'producto_id'))->get()->keyBy('id');
        foreach ($filas as &$fila) {
            $producto = $productos->get($fila['producto_id']);
            $fila['codigo'] = $fila['codigo'] ?: $producto?->codigo;
            $fila['producto'] = $fila['producto'] ?: $producto?->descripcion;
            $fila['unidad'] = $fila['unidad'] ?: $producto?->unidadMedida?->codigo;
            // No se agrega el malogrado: ya está incluido en las salidas.
            $fila['real'] = round($fila['salida'] - $fila['retorno'], 3);
            $fila['costo_real'] = round($fila['costo_salida'] - $fila['costo_retorno'], 4);
            $fila['diferencia'] = round($fila['real'] - $fila['estimado'], 3);
            $fila['diferencia_costo'] = $fila['costo_estimado'] === null ? null : round($fila['costo_real'] - $fila['costo_estimado'], 4);
            if ($fila['real'] < 0 || $fila['costo_real'] < 0) {
                $avisos[] = 'Hay retornos superiores a las salidas. Revisar movimientos; el reporte no oculta la diferencia.';
            }
        }
        unset($fila);
        foreach ($movimientos as &$movimiento) {
            $fila = $filas[$movimiento['clave']];
            $movimiento += array_intersect_key($fila, array_flip(['orden', 'area', 'codigo', 'producto', 'unidad']));
        }
        unset($movimiento);
        $materiales = collect($filas)->sortBy(fn($f) => $f['orden'] . '|' . $f['area'] . '|' . $f['codigo'])->values();
        $cotizacion = $raiz->cotizacionVinculada();
        $presupuestos = collect();
        if ($raiz->presupuesto_servicio_origen_id) {
            $presupuestos = CotizacionPresupuesto::whereKey($raiz->presupuesto_servicio_origen_id)->get();
        } elseif ($cotizacion) {
            $presupuestos = $cotizacion->presupuestos()->where('estado', 'VIGENTE')->where('tipo_costo', '!=', 'MATERIAL')->get();
        }
        $otrosEstimados = $presupuestos->map(function ($p) use ($raiz, $areaIdentificada): array {
            $areaOrden = $p->cotizacion_area_id
                ? $raiz->todasLasAreas->firstWhere('cotizacion_area_id', $p->cotizacion_area_id)
                : null;
            if ($areaOrden) {
                $area = $areaIdentificada($raiz, $areaOrden->id, null);
            } elseif ($p->cotizacion_area_id) {
                $area = ['clave' => $raiz->id . '|cotizacion:' . $p->cotizacion_area_id,
                    'nombre' => ($p->grupo_costo ?: 'SIN ÁREA EN LA ORDEN') . ' · área de la cotización'];
            } else {
                // Un grupo antiguo escrito a mano no prueba que pertenezca al área homónima.
                $grupo = trim((string) $p->grupo_costo) ?: 'SIN ÁREA REGISTRADA';
                $area = ['clave' => $raiz->id . '|presupuesto-sin-vinculo:' . mb_strtoupper($grupo),
                    'nombre' => $grupo . ' · sin vínculo'];
            }

            return [
                'orden_id' => $raiz->id,
                'orden' => $raiz->codigo_orden,
                'area_clave' => $area['clave'],
                'area' => $area['nombre'],
                'tipo' => $p->tipo_costo,
                'descripcion' => $p->descripcion,
                'ejecucion' => $p->ejecucion_servicio,
                'importe' => (float) $p->costo_total_soles,
            ];
        })->values();
        $otrosReales = CostoDirectoOrden::whereIn('orden_operacion_id', $ordenes->keys())->where('estado', 'VIGENTE')->orderBy('id')->get()
            ->map(function ($c) use ($ordenes, $areaIdentificada): array {
                $area = $areaIdentificada($ordenes->get($c->orden_operacion_id), $c->orden_area_id, null);

                return [
                    'orden_id' => $c->orden_operacion_id,
                    'orden' => $ordenes->get($c->orden_operacion_id)->codigo_orden,
                    'area_clave' => $area['clave'],
                    'area' => $area['nombre'],
                    'tipo' => $c->tipo,
                    'descripcion' => $c->descripcion,
                    'documento' => $c->documento_referencia,
                    'fecha' => $c->fecha_costo?->format('Y-m-d'),
                    'cantidad' => (float) $c->cantidad,
                    'unidad' => $c->unidadVisible(),
                    'importe' => (float) $c->total_soles,
                ];
            });
        $areas = $materiales->groupBy('area_clave')->map(fn($grupo): array => [
            'orden_id' => $grupo->first()['orden_id'],
            'orden' => $grupo->first()['orden'],
            'area' => $grupo->first()['area'],
            'estimado' => $grupo->contains(fn($f) => $f['costo_estimado'] === null) ? null : round($grupo->sum('costo_estimado'), 4),
            'real' => round($grupo->sum('costo_real'), 4),
            'diferencia' => $grupo->contains(fn($f) => $f['costo_estimado'] === null) ? null : round($grupo->sum('costo_real') - $grupo->sum('costo_estimado'), 4),
            'otros_estimados' => 0.0,
            'otros_reales' => 0.0,
        ])->all();
        foreach (['otros_estimados' => $otrosEstimados, 'otros_reales' => $otrosReales] as $campo => $costos) {
            foreach ($costos as $costo) {
                $clave = $costo['area_clave'];
                $areas[$clave] ??= [
                    'orden_id' => $costo['orden_id'],
                    'orden' => $costo['orden'], 'area' => $costo['area'],
                    'estimado' => 0.0, 'real' => 0.0, 'diferencia' => 0.0,
                    'otros_estimados' => 0.0, 'otros_reales' => 0.0,
                ];
                $areas[$clave][$campo] += $costo['importe'];
            }
        }
        foreach ($areas as &$area) {
            $area['otros_estimados'] = round($area['otros_estimados'], 4);
            $area['otros_reales'] = round($area['otros_reales'], 4);
            $esDesgloseOs = $ordenes->get($area['orden_id'])->orden_padre_id !== null;
            // El material planificado dentro de una OS hija está cubierto por el
            // servicio cotizado. Sigue visible como referencia, sin sumarse otra vez.
            $area['total_estimado'] = $esDesgloseOs
                ? $area['otros_estimados']
                : ($area['estimado'] === null ? null : round($area['estimado'] + $area['otros_estimados'], 4));
            $area['total_real'] = round($area['real'] + $area['otros_reales'], 4);
            $area['diferencia_total'] = $esDesgloseOs || $area['total_estimado'] === null
                ? null : round($area['total_real'] - $area['total_estimado'], 4);
            $area['criterio'] = $esDesgloseOs
                ? 'OS interna: materiales planificados informativos; sin diferencia total por área'
                : 'Presupuesto de la orden principal';
        }
        unset($area);
        $areas = collect($areas)->sortBy(fn($area) => $area['orden'] . '|' . $area['area'])->values();
        $faltanMaterialesCongelados = ! $raiz->orden_padre_id && $cotizacion
            && $raiz->materialesPlanificadosPorArea->isEmpty()
            && $cotizacion->presupuestos()->where('estado', 'VIGENTE')->where('tipo_costo', 'MATERIAL')->exists();
        // El presupuesto del servicio interno ya está en la principal: su desglose no se suma otra vez.
        $materialesBase = $materiales->where('orden_id', $raiz->id);
        $materialesSinCosto = $faltanMaterialesCongelados || $materialesBase->contains(fn($f) => $f['costo_estimado'] === null);
        if ($faltanMaterialesCongelados) {
            $avisos[] = 'La cotización tiene materiales pero falta su planificación congelada por área. Estimación no disponible.';
        }
        $estimacionCompleta = $cotizacion !== null && ! $materialesSinCosto;
        $costoEstimado = $estimacionCompleta ? round($materialesBase->sum('costo_estimado') + $otrosEstimados->sum('importe'), 4) : null;
        if ($raiz->presupuesto_servicio_origen_id) {
            $costoEstimado = $otrosEstimados->isEmpty() ? null : round($otrosEstimados->sum('importe'), 4);
        }
        $costoReal = round($materiales->sum('costo_real') + $otrosReales->sum('importe'), 4);
        $serviciosInternos = $ordenes
            ->filter(fn(OrdenOperacion $orden): bool => $orden->id !== $raiz->id && $orden->orden_padre_id !== null)
            ->map(function (OrdenOperacion $orden) use ($ordenes, $materiales, $otrosReales, $areaIdentificada): array {
                $origen = $orden->presupuestoServicioOrigen;
                $padre = $ordenes->get($orden->orden_padre_id);
                $areaPadre = $padre && $origen?->cotizacion_area_id
                    ? $padre->todasLasAreas->firstWhere('cotizacion_area_id', $origen->cotizacion_area_id)
                    : null;
                $area = $areaPadre
                    ? $areaIdentificada($padre, $areaPadre->id, null)['nombre']
                    : (trim((string) $origen?->grupo_costo) ?: 'SIN ÁREA VINCULADA').' · sin vínculo';
                $materialesReales = round($materiales->where('orden_id', $orden->id)->sum('costo_real'), 4);
                $costosDirectosReales = round($otrosReales->where('orden_id', $orden->id)->sum('importe'), 4);
                $estimado = $origen ? (float) $origen->costo_total_soles : null;
                $real = round($materialesReales + $costosDirectosReales, 4);

                return [
                    'orden' => $orden->codigo_orden,
                    'area' => $area,
                    'servicio' => $origen?->descripcion ?: $orden->descripcion,
                    'estimado' => $estimado,
                    'materiales_reales' => $materialesReales,
                    'otros_reales' => $costosDirectosReales,
                    'total_real' => $real,
                    'diferencia' => $estimado === null ? null : round($real - $estimado, 4),
                ];
            })->values()->all();
        $ingreso = null;
        if ($cotizacion && ! $raiz->orden_padre_id && (int) $cotizacion->orden_operacion_id === $raiz->id) {
            $factor = $cotizacion->moneda === 'USD' ? (float) $cotizacion->tipo_cambio : 1.0;
            if ($factor > 0) {
                $ingreso = round((float) $cotizacion->subtotal * $factor, 4);
            } else {
                $avisos[] = 'No hay un tipo de cambio válido para calcular el ingreso en soles.';
            }
        }
        $avisos[] = 'Gasto real registrado al momento de la consulta; los costos pendientes no se convierten automáticamente en gasto real.';
        $avisos[] = 'Importes en PEN según costos históricos guardados. Malogrados informativos: no se suman nuevamente. Los costos directos históricos sin área se identifican por separado.';
        $avisos[] = 'El total estimado toma el presupuesto de la orden consultada una sola vez. La planificación de sus OS es un desglose interno, no un presupuesto adicional. En una OS con servicio de origen, ese servicio es su presupuesto total.';
        $avisos[] = 'En la hoja Áreas, el material estimado de una OS hija es informativo; su presupuesto ya está en el servicio. La diferencia total de la OS por área es N/D porque no se reparte el precio del servicio entre sus áreas.';
        $avisos[] = 'OS internas por área de origen es un desglose informativo. Sus gastos ya están incluidos en el costo real total y no deben sumarse de nuevo.';
        return [
            'generado_en' => now()->format('Y-m-d H:i:s'),
            'orden' => $raiz->codigo_orden,
            'materiales' => $materiales->all(),
            'areas' => $areas->all(),
            'movimientos' => $movimientos,
            'otros_estimados' => $otrosEstimados->all(),
            'otros_reales' => $otrosReales->all(),
            'servicios_internos' => $serviciosInternos,
            'avisos' => array_values(array_unique($avisos)),
            'totales' => [
                'materiales_estimados' => $materialesSinCosto ? null : round($materialesBase->sum('costo_estimado'), 4),
                'materiales_reales' => round($materiales->sum('costo_real'), 4),
                'otros_estimados' => round($otrosEstimados->sum('importe'), 4),
                'otros_reales' => round($otrosReales->sum('importe'), 4),
                'costo_estimado' => $costoEstimado,
                'costo_real' => $costoReal,
                'diferencia' => $costoEstimado === null ? null : round($costoReal - $costoEstimado, 4),
                'ingreso' => $ingreso,
                'utilidad_estimada' => $ingreso === null || $costoEstimado === null ? null : round($ingreso - $costoEstimado, 4),
                'utilidad_registrada' => $ingreso === null ? null : round($ingreso - $costoReal, 4),
            ],
            'ordenes' => $ordenes->map(fn($o) => [
                'codigo' => $o->codigo_orden,
                'estado' => $o->estado,
                'materiales' => round($materiales->where('orden_id', $o->id)->sum('costo_real'), 4),
                'directos' => round($otrosReales->where('orden', $o->codigo_orden)->sum('importe'), 4),
                'cierre_guardado' => $o->costo_real_cierre_soles === null ? null : (float) $o->costo_real_cierre_soles,
            ])->values()->all(),
        ];
    }
}
