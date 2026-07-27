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
        Schema::create('cliente_direcciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();

            $table->string('ciudad', 100)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->string('direccion', 350)->nullable();
            $table->string('destino', 150)->nullable();
            $table->string('referencia', 250)->nullable();
            $table->boolean('es_principal')->default(false);
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->index(['cliente_id', 'estado']);
            $table->index('ciudad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::dropIfExists('cliente_direcciones');
    }
};
