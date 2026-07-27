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
        Schema::create('ordenes_operacion', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tipo_orden_id')
                ->constrained('tipos_orden')
                ->restrictOnDelete();

            $table->foreignId('cliente_id')
                ->nullable()
                ->constrained('clientes')
                ->restrictOnDelete();

            $table->foreignId('cliente_direccion_id')
                ->nullable()
                ->constrained('cliente_direcciones')
                ->restrictOnDelete();

            $table->foreignId('vehiculo_id')
                ->nullable()
                ->constrained('vehiculos')
                ->restrictOnDelete();

            $table->string('codigo_orden', 40)->unique();
            $table->unsignedInteger('numero_correlativo')->nullable();
            $table->unsignedSmallInteger('anio')->nullable();
            $table->date('fecha_apertura');
            $table->string('descripcion', 500)->nullable();
            $table->string('estado', 30)->default('ABIERTA');

            $table->foreignId('creado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('cerrado_en')->nullable();

            $table->foreignId('anulado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('anulado_en')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->unique(
                ['tipo_orden_id', 'numero_correlativo', 'anio'],
                'uq_tipo_correlativo_anio'
            );

            $table->index(['estado', 'fecha_apertura']);
            $table->index('cliente_id');
            $table->index('vehiculo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::dropIfExists('ordenes_operacion');
    }
};
