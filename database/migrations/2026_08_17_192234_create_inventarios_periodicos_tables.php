<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventarios_periodicos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->foreignId('repisa_id')
                ->constrained('repisas')
                ->restrictOnDelete();
            $table->timestamp('fecha_corte')->useCurrent();
            $table->string('estado', 20)->default('ABIERTO');
            $table->string('observacion', 500)->nullable();
            $table->unsignedInteger('total_lineas')->default(0);
            $table->unsignedInteger('lineas_con_diferencia')->default(0);
            $table->decimal('valor_sistema_soles', 16, 4)->default(0);
            $table->decimal('valor_diferencia_soles', 16, 4)->default(0);
            $table->foreignId('abierto_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('abierto_en')->useCurrent();
            $table->foreignId('cerrado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('cerrado_en')->nullable();
            $table->foreignId('anulado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index(['estado', 'fecha_corte'], 'idx_inv_periodico_estado_fecha');
            $table->index(['repisa_id', 'estado'], 'idx_inv_periodico_repisa_estado');
        });

        Schema::create('inventario_periodico_detalles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventario_periodico_id')
                ->constrained('inventarios_periodicos')
                ->restrictOnDelete();
            $table->foreignId('inventario_id')
                ->constrained('inventarios')
                ->restrictOnDelete();
            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();
            $table->foreignId('repisa_id')
                ->constrained('repisas')
                ->restrictOnDelete();
            $table->decimal('stock_sistema', 14, 3);
            $table->decimal('stock_contado', 14, 3)->nullable();
            $table->decimal('diferencia', 14, 3)->default(0);
            $table->decimal('costo_promedio_soles', 14, 4)->default(0);
            $table->decimal('valor_sistema_soles', 16, 4)->default(0);
            $table->decimal('valor_diferencia_soles', 16, 4)->default(0);
            $table->unsignedBigInteger('movimiento_id_corte')->nullable();
            $table->string('observacion', 300)->nullable();
            $table->foreignId('contado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('contado_en')->nullable();
            $table->timestamps();

            $table->unique(
                ['inventario_periodico_id', 'inventario_id'],
                'uq_inv_periodico_inventario'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_periodico_detalles');
        Schema::dropIfExists('inventarios_periodicos');
    }
};
