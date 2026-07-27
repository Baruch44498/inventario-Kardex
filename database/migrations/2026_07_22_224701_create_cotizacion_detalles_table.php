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
        Schema::create('cotizacion_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cotizacion_id')
                ->constrained('cotizaciones')
                ->cascadeOnDelete();

            $table->foreignId('requisicion_detalle_id')
                ->nullable()
                ->constrained('requisicion_detalles')
                ->restrictOnDelete();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();

            $table->decimal('cantidad', 14, 3);
            $table->decimal('precio_unitario', 14, 4);
            $table->decimal('descuento_porcentaje', 7, 4)->default(0);
            $table->decimal('subtotal', 14, 4);
            $table->string('marca_ofertada', 120)->nullable();
            $table->string('observacion', 300)->nullable();
            $table->timestamps();

            $table->unique(
                ['cotizacion_id', 'requisicion_detalle_id'],
                'uq_cotizacion_requisicion_detalle'
            );

            $table->index('producto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizacion_detalles');
    }
};
