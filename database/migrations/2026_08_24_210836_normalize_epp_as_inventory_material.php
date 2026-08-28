<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cotizacion_presupuestos')) {
            DB::table('cotizacion_presupuestos')
                ->where('tipo_costo', 'EPP_CONSUMIBLES')
                ->whereNull('grupo_costo')
                ->update(['grupo_costo' => 'EPP Y CONSUMIBLES']);

            DB::table('cotizacion_presupuestos')
                ->where('tipo_costo', 'EPP_CONSUMIBLES')
                ->whereNotNull('producto_id')
                ->update(['tipo_costo' => 'MATERIAL']);

            DB::table('cotizacion_presupuestos')
                ->where('tipo_costo', 'EPP_CONSUMIBLES')
                ->whereNull('producto_id')
                ->update(['tipo_costo' => 'OTRO', 'unidad' => 'GLOBAL']);

            DB::table('cotizacion_presupuestos')
                ->where('tipo_costo', 'MATERIAL')
                ->whereNull('producto_id')
                ->update(['tipo_costo' => 'OTRO', 'unidad' => 'GLOBAL']);

            $this->normalizarUnidadesMateriales('cotizacion_presupuestos');
        }

        if (Schema::hasTable('plantilla_costeo_partidas')) {
            DB::table('plantilla_costeo_partidas')
                ->where('tipo_costo', 'EPP_CONSUMIBLES')
                ->whereNull('grupo_costo')
                ->update(['grupo_costo' => 'EPP Y CONSUMIBLES']);

            DB::table('plantilla_costeo_partidas')
                ->where('tipo_costo', 'EPP_CONSUMIBLES')
                ->whereNotNull('producto_id')
                ->update(['tipo_costo' => 'MATERIAL']);

            DB::table('plantilla_costeo_partidas')
                ->where('tipo_costo', 'EPP_CONSUMIBLES')
                ->whereNull('producto_id')
                ->update(['tipo_costo' => 'OTRO', 'unidad' => 'GLOBAL']);

            DB::table('plantilla_costeo_partidas')
                ->where('tipo_costo', 'MATERIAL')
                ->whereNull('producto_id')
                ->update(['tipo_costo' => 'OTRO', 'unidad' => 'GLOBAL']);

            $this->normalizarUnidadesMateriales('plantilla_costeo_partidas');
        }

        if (Schema::hasTable('costos_directos_orden')) {
            DB::table('costos_directos_orden')
                ->where('tipo', 'EPP_CONSUMIBLES')
                ->update(['tipo' => 'OTRO']);
        }
    }

    public function down(): void
    {
        // La reclasificación es deliberadamente irreversible: no es posible
        // distinguir con certeza qué filas OTRO fueron antes EPP manuales.
    }

    private function normalizarUnidadesMateriales(string $tabla): void
    {
        $unidades = DB::table('productos as p')
            ->join('unidades_medida as u', 'u.id', '=', 'p.unidad_medida_id')
            ->pluck('u.codigo', 'p.id')
            ->map(fn($codigo): string => strtoupper(trim((string) $codigo)));

        DB::table($tabla)
            ->where('tipo_costo', 'MATERIAL')
            ->whereNotNull('producto_id')
            ->orderBy('id')
            ->chunkById(200, function ($filas) use ($tabla, $unidades): void {
                foreach ($filas as $fila) {
                    $unidad = $unidades->get($fila->producto_id);
                    if ($unidad) {
                        DB::table($tabla)
                            ->where('id', $fila->id)
                            ->update(['unidad' => $unidad]);
                    }
                }
            });
    }
};
