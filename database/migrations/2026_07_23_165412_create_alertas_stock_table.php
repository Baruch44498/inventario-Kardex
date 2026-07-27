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
         Schema::create('alertas_stock', function (Blueprint $table) {
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

            $table->string('tipo_alerta', 30);
            $table->string('nivel', 20);
            $table->decimal('stock_actual', 14, 3);
            $table->decimal('stock_minimo', 14, 3);
            $table->string('mensaje', 500);
            $table->string('estado', 20)->default('ACTIVA');
            $table->timestamp('detectada_en');

            $table->foreignId('atendida_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('atendida_en')->nullable();

            $table->foreignId('resuelta_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('resuelta_en')->nullable();
            $table->string('observacion_resolucion', 500)->nullable();
            $table->timestamps();

            $table->index(
                ['inventario_id', 'estado'],
                'idx_alerta_inventario_estado'
            );

            $table->index(
                ['tipo_alerta', 'estado', 'detectada_en'],
                'idx_alerta_tipo_estado_fecha'
            );

            $table->index(['producto_id', 'repisa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alertas_stock');
    }
};
