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
        Schema::create('cliente_destinos', function (Blueprint $table) {
        $table->id();
        $table->string('ruc', 20)->nullable();
        $table->string('razon_social'); // Ej: FABRICACION EN STOCK o EMPRESA DE TRANSPORTE
        $table->string('tipo_destino')->default('EXTERNO'); // INTERNO o EXTERNO
        $table->string('direccion')->nullable();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_destinos');
    }
};
