<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventario_id')
                ->constrained('inventarios')
                ->restrictOnDelete();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();

            $table->foreignId('repisa_id')
                ->constrained('repisas')
                ->restrictOnDelete();

            $table->string('tipo_movimiento', 30);
            $table->string('motivo', 40);
            $table->string('origen_tipo', 40);
            $table->unsignedBigInteger('origen_id');
            $table->unsignedBigInteger('origen_detalle_id')->nullable();

            $table->decimal('cantidad', 14, 3);
            $table->decimal('stock_anterior', 14, 3);
            $table->decimal('stock_posterior', 14, 3);
            $table->decimal('costo_unitario', 14, 4)->nullable();
            $table->decimal('costo_promedio_anterior', 14, 4)->default(0);
            $table->decimal('costo_promedio_nuevo', 14, 4)->default(0);
            $table->timestamp('fecha_movimiento');
            $table->string('observacion', 300)->nullable();

            $table->foreignId('registrado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index(['producto_id', 'fecha_movimiento']);
            $table->index(['repisa_id', 'fecha_movimiento']);
            $table->index(['tipo_movimiento', 'fecha_movimiento']);
            $table->index(['origen_tipo', 'origen_id'], 'idx_movimiento_origen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
