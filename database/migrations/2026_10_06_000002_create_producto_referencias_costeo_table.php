<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_referencias_costeo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('origen', 60);
            $table->unsignedInteger('fila_excel');
            $table->string('codigo_original', 50)->nullable();
            $table->decimal('cantidad_referencial', 14, 3);
            $table->decimal('costo_unitario_pen', 18, 6);
            $table->decimal('margen_porcentaje', 7, 4);
            $table->string('observacion', 255)->nullable();
            $table->timestamps();
            $table->unique(['origen', 'fila_excel'], 'uq_producto_referencia_origen_fila');
            $table->index(['producto_id', 'origen'], 'idx_producto_referencia_producto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_referencias_costeo');
    }
};
