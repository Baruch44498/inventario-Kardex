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
        Schema::create('nota_ingreso_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nota_ingreso_id')
                ->constrained('notas_ingreso')
                ->cascadeOnDelete();

            $table->foreignId('orden_compra_detalle_id')
                ->nullable()
                ->constrained('orden_compra_detalles')
                ->restrictOnDelete();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();

            $table->foreignId('repisa_id')
                ->constrained('repisas')
                ->restrictOnDelete();

            $table->decimal('cantidad', 14, 3);
            $table->decimal('costo_unitario', 14, 4);
            $table->decimal('subtotal', 14, 4);
            $table->string('lote', 80)->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('observacion', 300)->nullable();
            $table->timestamps();

            $table->index(['nota_ingreso_id', 'producto_id']);
            $table->index(['producto_id', 'repisa_id']);
            $table->index('orden_compra_detalle_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nota_ingreso_detalles');
    }
};
