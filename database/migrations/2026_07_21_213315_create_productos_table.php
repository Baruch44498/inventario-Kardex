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
        Schema::create('productos', function (Blueprint $table) {
        $table->id();
        $table->string('codigo')->unique(); // El código del producto (ej: 10004)
        $table->string('descripcion');
        $table->string('unidad_medida', 10)->default('UND');
        $table->integer('stock_actual')->default(0);
        $table->integer('stock_minimo')->default(0);
        $table->decimal('precio_unitario_soles', 10, 2)->nullable();
        $table->timestamps(); // Crea automáticamente 'created_at' y 'updated_at'
     });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
