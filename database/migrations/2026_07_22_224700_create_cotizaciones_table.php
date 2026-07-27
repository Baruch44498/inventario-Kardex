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
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('requisicion_id')
                ->constrained('requisiciones')
                ->restrictOnDelete();

            $table->foreignId('proveedor_id')
                ->constrained('proveedores')
                ->restrictOnDelete();

            $table->string('codigo', 40)->unique();
            $table->string('numero_documento', 60)->nullable();
            $table->date('fecha_cotizacion');
            $table->date('fecha_validez')->nullable();
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 14, 6)->nullable();
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('impuesto', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->string('condiciones_pago', 500)->nullable();
            $table->string('condiciones_entrega', 500)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->string('estado', 30)->default('REGISTRADA');

            $table->foreignId('registrado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('evaluado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('evaluado_en')->nullable();
            $table->timestamps();

            $table->index(['requisicion_id', 'estado']);
            $table->index(['proveedor_id', 'fecha_cotizacion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
