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
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->nullable()
                ->constrained('clientes')
                ->nullOnDelete();

            $table->string('placa', 20)->nullable()->unique();
            $table->string('codigo_interno', 40)->nullable()->unique();
            $table->string('marca', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('descripcion', 250)->nullable();
            $table->string('procedencia', 150)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->index(['cliente_id', 'estado']);
            $table->index('procedencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
