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
                ->where('tipo_costo', 'HERRAMIENTA_EQUIPO')
                ->update([
                    'tipo_costo' => 'SERVICIO_TERCERO',
                    'grupo_costo' => DB::raw(
                        "CASE WHEN grupo_costo IS NULL OR TRIM(grupo_costo) = '' " .
                            "THEN 'ALQUILER DE EQUIPO' ELSE grupo_costo END"
                    ),
                    'unidad' => DB::raw(
                        "CASE WHEN unidad IN ('HORA', 'DIA', 'SERVICIO', 'GLOBAL') " .
                            "THEN unidad ELSE 'GLOBAL' END"
                    ),
                ]);
        }

        if (Schema::hasTable('plantilla_costeo_partidas')) {
            DB::table('plantilla_costeo_partidas')
                ->where('tipo_costo', 'HERRAMIENTA_EQUIPO')
                ->update([
                    'tipo_costo' => 'SERVICIO_TERCERO',
                    'grupo_costo' => DB::raw(
                        "CASE WHEN grupo_costo IS NULL OR TRIM(grupo_costo) = '' " .
                            "THEN 'ALQUILER DE EQUIPO' ELSE grupo_costo END"
                    ),
                    'unidad' => DB::raw(
                        "CASE WHEN unidad IN ('HORA', 'DIA', 'SERVICIO', 'GLOBAL') " .
                            "THEN unidad ELSE 'GLOBAL' END"
                    ),
                ]);
        }

        if (Schema::hasTable('costos_directos_orden')) {
            DB::table('costos_directos_orden')
                ->where('tipo', 'HERRAMIENTA_EQUIPO')
                ->update([
                    'tipo' => 'SERVICIO_TERCERO',
                    'unidad' => DB::raw(
                        "CASE WHEN unidad IN ('HORA', 'DIA', 'SERVICIO', 'GLOBAL') " .
                            "THEN unidad ELSE 'GLOBAL' END"
                    ),
                ]);
        }
    }

    public function down(): void
    {
        // No se revierte automáticamente: no es posible distinguir con certeza
        // un alquiler histórico de una herramienta propia registrada por error.
    }
};
