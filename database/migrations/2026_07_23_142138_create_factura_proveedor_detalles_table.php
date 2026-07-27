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
        Schema::create('factura_proveedor_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('factura_proveedor_id')
                ->constrained('facturas_proveedor')
                ->cascadeOnDelete();

            $table->foreignId('orden_compra_detalle_id')
                ->nullable()
                ->constrained('orden_compra_detalles')
                ->restrictOnDelete();

            $table->foreignId('producto_id')
                ->nullable()
                ->constrained('productos')
                ->restrictOnDelete();

            $table->string('descripcion', 350);
            $table->decimal('cantidad', 14, 3);
            $table->decimal('precio_unitario', 14, 4);
            $table->decimal('descuento_porcentaje', 7, 4)->default(0);
            $table->decimal('subtotal', 14, 4);
            $table->string('observacion', 300)->nullable();
            $table->timestamps();

            $table->index('orden_compra_detalle_id');
            $table->index('producto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factura_proveedor_detalles');
    }
};
