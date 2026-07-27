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
        Schema::create('nota_salida_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nota_salida_id')
                ->constrained('notas_salida')
                ->cascadeOnDelete();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();

            $table->foreignId('repisa_id')
                ->constrained('repisas')
                ->restrictOnDelete();

            $table->decimal('cantidad', 14, 3);
            $table->decimal('costo_unitario_promedio', 14, 4);
            $table->decimal('subtotal', 14, 4);
            $table->string('observacion', 300)->nullable();
            $table->timestamps();

            $table->unique(
                ['nota_salida_id', 'producto_id', 'repisa_id'],
                'uq_nota_salida_producto_repisa'
            );

            $table->index(['producto_id', 'repisa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nota_salida_detalles');
    }
};
