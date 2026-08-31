<?php

namespace App\Services\Ordenes;

use App\Models\CotizacionArea;
use App\Models\CotizacionCliente;
use App\Models\CotizacionPresupuesto;
use App\Models\MaterialPlanificadoOrdenArea;
use App\Models\OrdenArea;
use App\Models\OrdenOperacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanificacionPorAreaService
{
    public const AREA_GENERAL = 'GENERAL';

    public function crearArea(
        CotizacionCliente $cotizacion,
        string $nombre,
        string $origen = 'MANUAL',
        ?CotizacionArea $padre = null
    ): CotizacionArea {
        $nombre = $this->limpiar($nombre);
        $normalizado = $this->normalizar($nombre);

        if ($nombre === '') {
            throw ValidationException::withMessages([
                'nombre' => 'El nombre del área es obligatorio.',
            ]);
        }
        if (! in_array($origen, CotizacionArea::ORIGENES, true)) {
            throw ValidationException::withMessages([
                'origen' => 'El origen del área no es válido.',
            ]);
        }
        if ($padre && $padre->cotizacion_cliente_id !== $cotizacion->id) {
            throw ValidationException::withMessages([
                'area_padre_id' => 'El área superior debe pertenecer a la misma cotización.',
            ]);
        }

        return DB::transaction(function () use ($cotizacion, $nombre, $normalizado, $origen, $padre): CotizacionArea {
            CotizacionCliente::query()->lockForUpdate()->findOrFail($cotizacion->id);

            $consulta = CotizacionArea::query()
                ->where('cotizacion_cliente_id', $cotizacion->id)
                ->where('nombre_normalizado', $normalizado)
                ->where('estado', 'VIGENTE');
            $padre
                ? $consulta->where('area_padre_id', $padre->id)
                : $consulta->whereNull('area_padre_id');

            $existente = $consulta->first();
            if ($existente) {
                return $existente;
            }

            $secuencia = CotizacionArea::query()
                ->where('cotizacion_cliente_id', $cotizacion->id)
                ->when(
                    $padre,
                    fn($query) => $query->where('area_padre_id', $padre->id),
                    fn($query) => $query->whereNull('area_padre_id')
                )
                ->max('orden_secuencia');

            return CotizacionArea::query()->create([
                'cotizacion_cliente_id' => $cotizacion->id,
                'area_padre_id' => $padre?->id,
                'nombre' => $nombre,
                'nombre_normalizado' => $normalizado,
                'orden_secuencia' => ((int) $secuencia) + 1,
                'origen' => $origen,
                'estado' => 'VIGENTE',
            ]);
        });
    }

    /**
     * Crea la fotografía inmutable de materiales estimados para una orden.
     * Las áreas siguen dentro de una sola OM, OS u OP; no generan órdenes separadas.
     */
    public function congelar(CotizacionCliente $cotizacion, OrdenOperacion $orden): int
    {
        return DB::transaction(function () use ($cotizacion, $orden): int {
            $orden = OrdenOperacion::query()
                ->with('tipoOrden')
                ->lockForUpdate()
                ->findOrFail($orden->id);

            if (! in_array($orden->tipoOrden?->codigo, ['OM', 'OS', 'OP'], true)) {
                throw ValidationException::withMessages([
                    'orden_operacion_id' => 'La planificación por áreas solo corresponde a órdenes OM, OS u OP.',
                ]);
            }
            if ($orden->cotizacion_cliente_id && $orden->cotizacion_cliente_id !== $cotizacion->id) {
                throw ValidationException::withMessages([
                    'cotizacion_cliente_id' => 'La cotización no corresponde a la orden seleccionada.',
                ]);
            }
            if ($orden->todasLasAreas()->exists() || $orden->materialesPlanificadosPorArea()->exists()) {
                throw ValidationException::withMessages([
                    'orden_operacion_id' => 'La planificación de esta orden ya fue congelada.',
                ]);
            }

            $this->validarServiciosClasificados($cotizacion);
            $this->asignarAreaGeneralAMaterialesSinArea($cotizacion);

            $areas = $cotizacion->todasLasAreas()
                ->where('estado', 'VIGENTE')
                ->orderBy('orden_secuencia')
                ->orderBy('id')
                ->get()
                ->keyBy('id');
            $areasOrden = [];

            $crearAreaOrden = function (CotizacionArea $area) use (&$crearAreaOrden, &$areasOrden, $areas, $orden): OrdenArea {
                if (isset($areasOrden[$area->id])) {
                    return $areasOrden[$area->id];
                }

                $padreOrden = null;
                if ($area->area_padre_id && $areas->has($area->area_padre_id)) {
                    $padreOrden = $crearAreaOrden($areas->get($area->area_padre_id));
                }

                return $areasOrden[$area->id] = OrdenArea::query()->create([
                    'orden_operacion_id' => $orden->id,
                    'cotizacion_area_id' => $area->id,
                    'area_padre_id' => $padreOrden?->id,
                    'nombre' => $area->nombre,
                    'nombre_normalizado' => $area->nombre_normalizado,
                    'orden_secuencia' => $area->orden_secuencia,
                    'origen' => 'COTIZACION',
                    'estado' => 'ACTIVA',
                ]);
            };

            $areas->each($crearAreaOrden);

            $partidas = CotizacionPresupuesto::query()
                ->with('producto.unidadMedida')
                ->where('cotizacion_cliente_id', $cotizacion->id)
                ->where('tipo_costo', 'MATERIAL')
                ->where('estado', 'VIGENTE')
                ->whereNotNull('cotizacion_area_id')
                ->whereNotNull('producto_id')
                ->get();

            $creados = 0;
            $partidas
                ->groupBy(fn(CotizacionPresupuesto $partida): string =>
                    $partida->cotizacion_area_id . ':' . $partida->producto_id
                )
                ->each(function (Collection $lineas) use (&$creados, $areasOrden, $orden): void {
                    /** @var CotizacionPresupuesto $primera */
                    $primera = $lineas->first();
                    $areaOrden = $areasOrden[$primera->cotizacion_area_id] ?? null;
                    if (! $areaOrden || ! $primera->producto) {
                        return;
                    }

                    $cantidad = round((float) $lineas->sum('cantidad'), 3);
                    $total = round((float) $lineas->sum('costo_total_soles'), 4);

                    MaterialPlanificadoOrdenArea::query()->create([
                        'orden_operacion_id' => $orden->id,
                        'orden_area_id' => $areaOrden->id,
                        'producto_id' => $primera->producto_id,
                        'codigo_producto' => $primera->producto->codigo,
                        'descripcion_producto' => $primera->producto->descripcion,
                        'unidad' => CotizacionPresupuesto::unidadDeProducto($primera->producto)
                            ?: $primera->unidad,
                        'cantidad_estimada' => $cantidad,
                        'costo_unitario_estimado_soles' => $cantidad > 0
                            ? round($total / $cantidad, 4)
                            : 0,
                        'costo_total_estimado_soles' => $total,
                        'congelado_en' => now(),
                    ]);
                    $creados++;
                });

            return $creados;
        });
    }

    public function validarServiciosClasificados(CotizacionCliente $cotizacion): void
    {
        $pendientes = CotizacionPresupuesto::query()
            ->where('cotizacion_cliente_id', $cotizacion->id)
            ->where('tipo_costo', 'SERVICIO_TERCERO')
            ->where('estado', 'VIGENTE')
            ->where(function ($query): void {
                $query->whereNull('ejecucion_servicio')
                    ->orWhereNotIn('ejecucion_servicio', ['EXTERNO', 'INTERNO_HIDROIL']);
            })
            ->count();

        if ($pendientes > 0) {
            throw ValidationException::withMessages([
                'servicios' => "Hay {$pendientes} servicio(s) sin clasificar como externo o interno HIDROIL.",
            ]);
        }
    }

    private function asignarAreaGeneralAMaterialesSinArea(CotizacionCliente $cotizacion): void
    {
        $consulta = CotizacionPresupuesto::query()
            ->where('cotizacion_cliente_id', $cotizacion->id)
            ->where('tipo_costo', 'MATERIAL')
            ->where('estado', 'VIGENTE')
            ->whereNull('cotizacion_area_id');

        if (! $consulta->exists()) {
            return;
        }

        $general = $this->crearArea($cotizacion, self::AREA_GENERAL, 'MANUAL');
        $consulta->update([
            'cotizacion_area_id' => $general->id,
            'grupo_costo' => self::AREA_GENERAL,
        ]);
    }

    public function normalizar(?string $valor): string
    {
        return mb_strtoupper($this->limpiar((string) $valor));
    }

    private function limpiar(string $valor): string
    {
        return trim(preg_replace('/\s+/u', ' ', $valor) ?? '');
    }
}
