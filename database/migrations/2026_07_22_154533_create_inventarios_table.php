<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();
            $table->foreignId('repisa_id')
                ->constrained('repisas')
                ->restrictOnDelete();
            $table->decimal('stock_actual', 14, 3)->default(0);
            $table->decimal('stock_minimo', 14, 3)->default(0);
            $table->decimal('stock_maximo', 14, 3)->nullable();
            $table->decimal('costo_promedio_soles', 14, 4)->default(0);
            $table->timestamps();
            $table->unique(
                ['producto_id', 'repisa_id'],
                'uq_inventario_producto_repisa'
            );
            $table->index(
                ['stock_actual', 'stock_minimo'],
                'idx_inventario_stock_minimo'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};
