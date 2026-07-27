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
        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id();

            $table->foreignId('solicitud_compra_id')
                ->unique()
                ->constrained('solicitudes_compra')
                ->restrictOnDelete();

            $table->foreignId('proveedor_id')
                ->constrained('proveedores')
                ->restrictOnDelete();

            $table->string('codigo', 40)->unique();
            $table->string('numero_documento_proveedor', 60)->nullable();
            $table->date('fecha_emision');
            $table->date('fecha_entrega_requerida')->nullable();
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 14, 6)->nullable();
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('impuesto', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->string('condiciones_pago', 500)->nullable();
            $table->string('condiciones_entrega', 500)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->string('estado', 30)->default('BORRADOR');

            $table->foreignId('emitido_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('aprobado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('aprobado_en')->nullable();

            $table->foreignId('anulado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('anulado_en')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index(['proveedor_id', 'fecha_emision']);
            $table->index(['estado', 'fecha_emision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordenes_compra');
    }
};
