<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas_materiales_orden', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orden_operacion_id')
                ->constrained('ordenes_operacion')
                ->restrictOnDelete();
            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();
            $table->decimal('cantidad_reservada', 14, 3);
            $table->decimal('cantidad_atendida', 14, 3)->default(0);
            $table->decimal('cantidad_liberada', 14, 3)->default(0);
            $table->string('estado', 20)->default('ACTIVA');
            $table->string('observacion', 500)->nullable();
            $table->foreignId('reservado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('actualizado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['orden_operacion_id', 'producto_id'],
                'uq_reserva_orden_producto'
            );
            $table->index(
                ['producto_id', 'estado'],
                'idx_reserva_producto_estado'
            );
            $table->index(
                ['orden_operacion_id', 'estado'],
                'idx_reserva_orden_estado'
            );
        });

        Schema::table('nota_salida_detalles', function (Blueprint $table): void {
            $table->foreignId('reserva_material_orden_id')
                ->nullable()
                ->after('proforma_detalle_id')
                ->constrained('reservas_materiales_orden')
                ->nullOnDelete();
            $table->decimal('cantidad_aplicada_reserva', 14, 3)
                ->default(0)
                ->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('nota_salida_detalles', function (Blueprint $table): void {
            if (Schema::hasColumn('nota_salida_detalles', 'reserva_material_orden_id')) {
                $table->dropConstrainedForeignId('reserva_material_orden_id');
            }
            if (Schema::hasColumn('nota_salida_detalles', 'cantidad_aplicada_reserva')) {
                $table->dropColumn('cantidad_aplicada_reserva');
            }
        });

        Schema::dropIfExists('reservas_materiales_orden');
    }
};
