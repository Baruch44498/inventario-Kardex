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
        Schema::create('notas_ingreso', function (Blueprint $table) {
            $table->id();

            $table->foreignId('orden_compra_id')
                ->constrained('ordenes_compra')
                ->restrictOnDelete();

            $table->foreignId('factura_proveedor_id')
                ->nullable()
                ->constrained('facturas_proveedor')
                ->restrictOnDelete();

            $table->string('codigo', 40)->unique();
            $table->date('fecha_ingreso');
            $table->string('numero_guia_remision', 60)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->string('estado', 30)->default('BORRADOR');

            $table->foreignId('registrado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('confirmado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('confirmado_en')->nullable();

            $table->foreignId('anulado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('anulado_en')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index(['orden_compra_id', 'estado']);
            $table->index(['fecha_ingreso', 'estado']);
            $table->index('factura_proveedor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notas_ingreso');
    }
};
