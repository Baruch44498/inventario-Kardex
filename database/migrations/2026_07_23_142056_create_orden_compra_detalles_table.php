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
        Schema::create('orden_compra_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('orden_compra_id')
                ->constrained('ordenes_compra')
                ->cascadeOnDelete();

            $table->foreignId('solicitud_compra_detalle_id')
                ->nullable()
                ->constrained('solicitud_compra_detalles')
                ->restrictOnDelete();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();

            $table->decimal('cantidad_ordenada', 14, 3);
            $table->decimal('cantidad_recibida', 14, 3)->default(0);
            $table->decimal('precio_unitario', 14, 4);
            $table->decimal('descuento_porcentaje', 7, 4)->default(0);
            $table->decimal('subtotal', 14, 4);
            $table->string('observacion', 300)->nullable();
            $table->timestamps();

            $table->unique(
                ['orden_compra_id', 'producto_id'],
                'uq_orden_compra_producto'
            );

            $table->unique(
                ['orden_compra_id', 'solicitud_compra_detalle_id'],
                'uq_orden_solicitud_detalle'
            );

            $table->index('producto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orden_compra_detalles');
    }
};
