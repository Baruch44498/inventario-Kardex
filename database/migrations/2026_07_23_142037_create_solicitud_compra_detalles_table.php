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
       Schema::create('solicitud_compra_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('solicitud_compra_id')
                ->constrained('solicitudes_compra')
                ->cascadeOnDelete();

            $table->foreignId('cotizacion_detalle_id')
                ->nullable()
                ->constrained('cotizacion_detalles')
                ->restrictOnDelete();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();

            $table->decimal('cantidad', 14, 3);
            $table->decimal('precio_unitario', 14, 4);
            $table->decimal('descuento_porcentaje', 7, 4)->default(0);
            $table->decimal('subtotal', 14, 4);
            $table->string('observacion', 300)->nullable();
            $table->timestamps();

            $table->unique(
                ['solicitud_compra_id', 'producto_id'],
                'uq_solicitud_compra_producto'
            );

            $table->unique(
                ['solicitud_compra_id', 'cotizacion_detalle_id'],
                'uq_solicitud_cotizacion_detalle'
            );

            $table->index('producto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_compra_detalles');
    }
};
