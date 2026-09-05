<?php

namespace App\Services\Ventas;

use App\Models\CotizacionCliente;
use App\Models\PlantillaCosteo;
use App\Services\Ordenes\PlanificacionPorAreaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EstructuraPlantillaCosteoService
{
    public function __construct(private readonly PlanificacionPorAreaService $planificacion) {}

    public function guardarAreas(CotizacionCliente $cotizacion, PlantillaCosteo $plantilla): array
    {
        $areas = $cotizacion->todasLasAreas()->where('estado', 'VIGENTE')->get();

        return $this->copiarArbol($areas, fn($area, $padre) => $plantilla->areas()->create([
            'nombre' => $area->nombre,
            'area_padre_id' => $padre?->id,
            'orden_secuencia' => $area->orden_secuencia,
        ]));
    }

    public function aplicarAreas(PlantillaCosteo $plantilla, CotizacionCliente $cotizacion): array
    {
        return $this->copiarArbol($plantilla->areas()->get(), fn($area, $padre) =>
            $this->planificacion->crearArea(
                $cotizacion,
                $area->nombre,
                $plantilla->origen === 'EXCEL' ? 'EXCEL' : 'MANUAL',
                $padre
            )
        );
    }

    /** Compatibilidad con Excel y plantillas anteriores sin relación de área. */
    public function completarGrupos(PlantillaCosteo $plantilla): void
    {
        $areas = $plantilla->areas()->whereNull('area_padre_id')->get()
            ->keyBy(fn($area) => $this->clave($area->nombre));
        $secuencia = (int) $areas->max('orden_secuencia');
        foreach ($plantilla->partidas()->whereNull('plantilla_area_id')
            ->whereIn('tipo_costo', ['MATERIAL', 'SERVICIO_TERCERO'])->get() as $partida) {
            $nombre = trim((string) $partida->grupo_costo);
            if ($nombre === '' && $partida->tipo_costo !== 'MATERIAL') {
                continue;
            }
            $nombre = $nombre !== '' ? $nombre : PlanificacionPorAreaService::AREA_GENERAL;
            $clave = $this->clave($nombre);
            if (! $areas->has($clave)) {
                $areas->put($clave, $plantilla->areas()->create([
                    'nombre' => $nombre,
                    'orden_secuencia' => ++$secuencia,
                ]));
            }
            $partida->update(['plantilla_area_id' => $areas->get($clave)->id]);
        }
    }

    private function clave(string $nombre): string
    {
        return Str::upper(Str::ascii(preg_replace('/\s+/u', ' ', trim($nombre))));
    }

    private function copiarArbol(Collection $areas, callable $crear): array
    {
        $origen = $areas->keyBy('id');
        $copias = [];
        $visitando = [];
        $copiar = function ($area) use (&$copiar, &$copias, &$visitando, $origen, $crear) {
            if (isset($copias[$area->id])) {
                return $copias[$area->id];
            }
            if (isset($visitando[$area->id]) || ($area->area_padre_id && ! $origen->has($area->area_padre_id))) {
                throw ValidationException::withMessages(['plantilla_id' => 'Revisa la jerarquía de las áreas antes de copiar la plantilla.']);
            }
            $visitando[$area->id] = true;
            $padre = $area->area_padre_id ? $copiar($origen->get($area->area_padre_id)) : null;
            $copias[$area->id] = $crear($area, $padre);
            unset($visitando[$area->id]);

            return $copias[$area->id];
        };
        foreach ($areas as $area) {
            $copiar($area);
        }

        return $copias;
    }
}
