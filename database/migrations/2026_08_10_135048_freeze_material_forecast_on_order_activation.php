<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ordenes_operacion', 'iniciado_en')) {
            Schema::table('ordenes_operacion', function (Blueprint $table): void {
                $table->timestamp('iniciado_en')->nullable()->after('estado');
            });
        }

        if (! Schema::hasColumn('ordenes_operacion', 'iniciado_por')) {
            Schema::table('ordenes_operacion', function (Blueprint $table): void {
                $table->foreignId('iniciado_por')->nullable()->after('iniciado_en');
                $table->foreign('iniciado_por', 'fk_orden_iniciado_por')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('materiales_requeridos_orden', 'cantidad_prevista')) {
            Schema::table('materiales_requeridos_orden', function (Blueprint $table): void {
                $table->decimal('cantidad_prevista', 14, 3)
                    ->nullable()
                    ->after('cantidad_requerida');
            });
        }

        // Las órdenes que ya estaban ejecutándose antes de este bloque no tienen
        // un instante histórico exacto de congelación. Se toma su requerimiento
        // actual como mejor aproximación de la previsión existente al migrar.
        $materialesHistoricos = DB::table('materiales_requeridos_orden as m')
            ->join('ordenes_operacion as o', 'o.id', '=', 'm.orden_operacion_id')
            ->whereNull('m.cantidad_prevista')
            ->whereIn('o.estado', ['EN_PROCESO', 'CERRADA', 'ANULADA'])
            ->pluck('m.id');

        if ($materialesHistoricos->isNotEmpty()) {
            DB::table('materiales_requeridos_orden')
                ->whereIn('id', $materialesHistoricos)
                ->update(['cantidad_prevista' => DB::raw('cantidad_requerida')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('materiales_requeridos_orden', 'cantidad_prevista')) {
            Schema::table('materiales_requeridos_orden', function (Blueprint $table): void {
                $table->dropColumn('cantidad_prevista');
            });
        }

        if (Schema::hasColumn('ordenes_operacion', 'iniciado_por')) {
            Schema::table('ordenes_operacion', function (Blueprint $table): void {
                $table->dropForeign('fk_orden_iniciado_por');
                $table->dropColumn('iniciado_por');
            });
        }

        if (Schema::hasColumn('ordenes_operacion', 'iniciado_en')) {
            Schema::table('ordenes_operacion', function (Blueprint $table): void {
                $table->dropColumn('iniciado_en');
            });
        }
    }
};
