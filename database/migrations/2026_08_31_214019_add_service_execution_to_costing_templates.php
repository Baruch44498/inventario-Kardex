<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantilla_costeo_partidas', function (Blueprint $table): void {
            $table->string('ejecucion_servicio', 25)
                ->nullable()
                ->after('tipo_costo');
        });

        Schema::table('importacion_plantilla_costeo_partidas', function (Blueprint $table): void {
            $table->string('ejecucion_servicio', 25)
                ->nullable()
                ->after('tipo_costo');
        });

        DB::table('plantilla_costeo_partidas')
            ->where('tipo_costo', 'SERVICIO_TERCERO')
            ->update(['ejecucion_servicio' => 'EXTERNO']);
    }

    public function down(): void
    {
        Schema::table('importacion_plantilla_costeo_partidas', function (Blueprint $table): void {
            $table->dropColumn('ejecucion_servicio');
        });
        Schema::table('plantilla_costeo_partidas', function (Blueprint $table): void {
            $table->dropColumn('ejecucion_servicio');
        });
    }
};
