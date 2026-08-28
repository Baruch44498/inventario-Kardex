<?php

namespace App\Services\Ordenes;

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
    public function materialesPlanificados(OrdenOperacion $orden, string $area): Collection
    {
        $area = $this->normalizar($area);
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
