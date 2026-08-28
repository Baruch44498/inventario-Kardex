<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nota_salida_detalles', function (Blueprint $table): void {
            $table->decimal('cantidad_planificada_aplicada', 14, 3)->nullable()->after('cantidad');
            $table->decimal('cantidad_excedente', 14, 3)->nullable()->after('cantidad_planificada_aplicada');
            $table->string('motivo_excedente', 35)->nullable()->after('cantidad_excedente');
            $table->index('motivo_excedente', 'idx_salida_detalle_motivo_excedente');
        });

        Schema::table('notas_ingreso', function (Blueprint $table): void {
            $table->foreignId('orden_operacion_id')
                ->nullable()
                ->after('orden_compra_id')
                ->constrained('ordenes_operacion')
                ->nullOnDelete();
            $table->string('area_trabajo', 150)->nullable()->after('orden_operacion_id');
            $table->foreignId('devuelto_por_empleado_id')
                ->nullable()
                ->after('proforma_id')
                ->constrained('empleados')
                ->nullOnDelete();
            $table->string('devuelto_por_nombre', 150)->nullable()->after('devuelto_por_empleado_id');
            $table->char('devuelto_por_dni', 8)->nullable()->after('devuelto_por_nombre');
            $table->index(
                ['orden_operacion_id', 'area_trabajo', 'estado'],
                'idx_ingreso_orden_area_estado'
            );
        });

        Schema::table('nota_ingreso_detalles', function (Blueprint $table): void {
            $table->string('condicion_retorno', 20)->nullable()->after('cantidad');
            $table->boolean('afecta_stock')->default(true)->after('condicion_retorno');
        });
    }

    public function down(): void
    {
        Schema::table('nota_ingreso_detalles', function (Blueprint $table): void {
            $table->dropColumn(['condicion_retorno', 'afecta_stock']);
        });

        Schema::table('notas_ingreso', function (Blueprint $table): void {
            $table->dropIndex('idx_ingreso_orden_area_estado');
            $table->dropForeign(['orden_operacion_id']);
            $table->dropForeign(['devuelto_por_empleado_id']);
            $table->dropColumn([
                'orden_operacion_id',
                'area_trabajo',
                'devuelto_por_empleado_id',
                'devuelto_por_nombre',
                'devuelto_por_dni',
            ]);
        });

        Schema::table('nota_salida_detalles', function (Blueprint $table): void {
            $table->dropIndex('idx_salida_detalle_motivo_excedente');
            $table->dropColumn([
                'cantidad_planificada_aplicada',
                'cantidad_excedente',
                'motivo_excedente',
            ]);
        });
    }
};
