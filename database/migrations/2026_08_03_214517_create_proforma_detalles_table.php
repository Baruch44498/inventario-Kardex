<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_detalles', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('proforma_id')
                ->constrained('proformas')
                ->cascadeOnDelete();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();

            // Copias históricas para que el documento no cambie si cambia el catálogo.
            $table->string('codigo_producto', 80);
            $table->string('descripcion', 500);
            $table->string('unidad_medida', 30)->nullable();

            $table->decimal('cantidad', 14, 3);
            $table->decimal('costo_referencia', 14, 4)->nullable();
            $table->decimal('margen_sugerido', 7, 4)->default(0);
            $table->decimal('precio_sugerido', 14, 4)->nullable();
            $table->decimal('precio_unitario', 14, 4);
            $table->string('igv_modo', 20)->default('AGREGAR');
            $table->decimal('igv_porcentaje', 7, 4)->default(18);
            $table->decimal('subtotal', 14, 4);
            $table->decimal('impuesto', 14, 4);
            $table->decimal('total', 14, 4);
            $table->string('observacion', 300)->nullable();
            $table->timestamps();

            $table->unique(
                ['proforma_id', 'producto_id'],
                'uq_proforma_producto'
            );
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_detalles');
    }
};
