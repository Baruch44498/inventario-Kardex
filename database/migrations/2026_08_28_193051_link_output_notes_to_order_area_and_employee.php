<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_salida', function (Blueprint $table): void {
            $table->string('area_trabajo', 150)->nullable()->after('orden_operacion_id');
            $table->foreignId('recibido_por_empleado_id')
                ->nullable()
                ->after('entregado_a')
                ->constrained('empleados')
                ->nullOnDelete();
            $table->string('recibido_por_nombre', 150)->nullable()->after('recibido_por_empleado_id');
            $table->char('recibido_por_dni', 8)->nullable()->after('recibido_por_nombre');
            $table->string('entregado_por_nombre', 150)->nullable()->after('recibido_por_dni');
            $table->char('entregado_por_dni', 8)->nullable()->after('entregado_por_nombre');
            $table->index(
                ['orden_operacion_id', 'area_trabajo', 'estado'],
                'idx_nota_salida_orden_area_estado'
            );
        });

        DB::table('notas_salida')
            ->whereNull('recibido_por_nombre')
            ->whereNotNull('entregado_a')
            ->update(['recibido_por_nombre' => DB::raw('entregado_a')]);
    }

    public function down(): void
    {
        Schema::table('notas_salida', function (Blueprint $table): void {
            $table->dropIndex('idx_nota_salida_orden_area_estado');
            $table->dropForeign(['recibido_por_empleado_id']);
            $table->dropColumn([
                'area_trabajo',
                'recibido_por_empleado_id',
                'recibido_por_nombre',
                'recibido_por_dni',
                'entregado_por_nombre',
                'entregado_por_dni',
            ]);
        });
    }
};
