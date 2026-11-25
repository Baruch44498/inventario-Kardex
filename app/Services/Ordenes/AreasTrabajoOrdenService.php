<?php

namespace App\Services\Ordenes;

use App\Models\OrdenArea;
use App\Models\OrdenOperacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AreasTrabajoOrdenService
{
    public const AREA_GENERAL = 'GENERAL';

    /**
     * @return Collection<int, string>
     */
    public function areas(OrdenOperacion $orden): Collection
    {
        $areasEstructuradas = $orden->todasLasAreas()
            ->where('estado', 'ACTIVA')
            ->orderBy('orden_secuencia')
            ->orderBy('id')
            ->pluck('nombre')
            ->map(fn(string $area): string => $this->normalizar($area))
            ->unique()
            ->values();

        if ($areasEstructuradas->isNotEmpty()) {
            return $areasEstructuradas;
        }

        $partidas = $this->partidasMateriales($orden);
        $areas = $partidas
            ->map(fn(object $partida): string => $this->normalizar($partida->grupo_costo))
            ->unique()
            ->values();

        $productosPresupuestados = $partidas
            ->pluck('producto_id')
            ->filter()
            ->map(fn($id): int => (int) $id)
            ->unique();
        $productosRequeridos = $orden->materialesRequeridos()
            ->whereNotNull('producto_id')
            ->pluck('producto_id')
            ->map(fn($id): int => (int) $id)
            ->unique();

        if ($areas->isEmpty() || $productosRequeridos->diff($productosPresupuestados)->isNotEmpty()) {
            $areas->push(self::AREA_GENERAL);
        }

        return $areas->unique()->values();
    }

    /**
     * Cantidades previstas por producto dentro del grupo elegido.
     *
     * @return Collection<int, float> Clave: producto_id.
     */
    public function materialesPlanificados(OrdenOperacion $orden, string $area, ?int $areaId = null): Collection
    {
        $area = $this->normalizar($area);

        $planEstructurado = DB::table('materiales_planificados_orden_area as mp')
            ->join('orden_areas as oa', 'oa.id', '=', 'mp.orden_area_id')
            ->where('mp.orden_operacion_id', $orden->id)
            ->where('oa.estado', 'ACTIVA')
            ->when($areaId, fn($query) => $query->where('oa.id', $areaId),
                fn($query) => $query->where('oa.nombre_normalizado', $area))
            ->selectRaw('mp.producto_id, SUM(mp.cantidad_estimada) as cantidad_estimada')
            ->groupBy('mp.producto_id')
            ->pluck('cantidad_estimada', 'mp.producto_id')
            ->map(fn($cantidad): float => round((float) $cantidad, 3));

        if ($planEstructurado->isNotEmpty() || $orden->todasLasAreas()->exists()) {
            return $planEstructurado;
        }

        $partidas = $this->partidasMateriales($orden);

        $cantidades = $partidas
            ->filter(fn(object $partida): bool => $this->normalizar($partida->grupo_costo) === $area)
            ->groupBy('producto_id')
            ->map(fn(Collection $filas): float => round((float) $filas->sum('cantidad'), 3));

        if ($area !== self::AREA_GENERAL) {
            return $cantidades;
        }

        $productosPresupuestados = $partidas
            ->pluck('producto_id')
            ->filter()
            ->map(fn($id): int => (int) $id)
            ->unique();

        $orden->materialesRequeridos()
            ->whereNotNull('producto_id')
            ->get(['producto_id', 'cantidad_requerida', 'cantidad_prevista'])
            ->each(function ($material) use ($cantidades, $productosPresupuestados): void {
                $productoId = (int) $material->producto_id;
                if (! $productosPresupuestados->contains($productoId)) {
                    $cantidades->put(
                        $productoId,
                        round((float) ($material->cantidad_prevista ?? $material->cantidad_requerida), 3)
                    );
                }
            });

        return $cantidades;
    }

    /** Cada ruta conserva el ID de su área, incluso si dos subáreas tienen el mismo nombre. */
    public function areasConRuta(OrdenOperacion $orden): Collection
    {
        $areas = $orden->todasLasAreas()->where('estado', 'ACTIVA')
            ->orderBy('orden_secuencia')->orderBy('id')->get()->keyBy('id');

        return $areas->values()->map(function (OrdenArea $area) use ($areas): array {
            $ruta = [$area->nombre];
            $padreId = $area->area_padre_id;
            $visitados = [$area->id];
            while ($padreId && $areas->has($padreId) && ! in_array($padreId, $visitados, true)) {
                $visitados[] = $padreId;
                $padre = $areas->get($padreId);
                array_unshift($ruta, $padre->nombre);
                $padreId = $padre->area_padre_id;
            }

            return ['id' => $area->id, 'nombre' => $area->nombre_normalizado,
                'ruta' => implode(' / ', $ruta)];
        });
    }

    public function resolver(OrdenOperacion $orden, ?string $area): ?string
    {
        $areas = $this->areas($orden);
        if ($areas->isEmpty()) {
            return null;
        }

        if (! filled($area)) {
            return $areas->count() === 1 ? $areas->first() : null;
        }

        $buscada = $this->normalizar($area);

        return $areas->first(fn(string $disponible): bool => $disponible === $buscada);
    }

    public function resolverRegistro(OrdenOperacion $orden, ?string $area, ?int $areaId = null): ?OrdenArea
    {
        if ($areaId) {
            return $orden->todasLasAreas()->where('estado', 'ACTIVA')->whereKey($areaId)->first();
        }
        $nombre = $this->resolver($orden, $area);
        if (! $nombre) {
            return null;
        }

        $coincidencias = $orden->todasLasAreas()
            ->where('estado', 'ACTIVA')
            ->where('nombre_normalizado', $nombre)
            ->limit(2)->get();

        // Los clientes antiguos pueden enviar nombres; si son ambiguos deben escoger el ID.
        return $coincidencias->count() === 1 ? $coincidencias->first() : null;
    }

    public function normalizar(?string $area): string
    {
        $area = preg_replace('/\s+/u', ' ', trim((string) $area));

        return $area === '' ? self::AREA_GENERAL : mb_strtoupper($area);
    }

    /**
     * @return Collection<int, object>
     */
    private function partidasMateriales(OrdenOperacion $orden): Collection
    {
        return DB::table('cotizacion_presupuestos as p')
            ->join('cotizacion_componentes as c', 'c.id', '=', 'p.componente_id')
            ->where('c.orden_operacion_id', $orden->id)
            ->where('p.estado', 'VIGENTE')
            ->where('p.tipo_costo', 'MATERIAL')
            ->whereNotNull('p.producto_id')
            ->orderBy('p.id')
            ->get(['p.producto_id', 'p.grupo_costo', 'p.cantidad']);
    }
}
