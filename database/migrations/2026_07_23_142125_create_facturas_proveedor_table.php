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
         Schema::create('facturas_proveedor', function (Blueprint $table) {
            $table->id();

            $table->foreignId('orden_compra_id')
                ->constrained('ordenes_compra')
                ->restrictOnDelete();

            $table->foreignId('proveedor_id')
                ->constrained('proveedores')
                ->restrictOnDelete();

            $table->string('tipo_documento', 20)->default('FACTURA');
            $table->string('serie', 20);
            $table->string('numero', 30);
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 14, 6)->nullable();
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('impuesto', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->string('observacion', 500)->nullable();
            $table->string('estado', 30)->default('REGISTRADA');

            $table->foreignId('registrado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('anulado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('anulado_en')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->unique(
                ['proveedor_id', 'tipo_documento', 'serie', 'numero'],
                'uq_factura_proveedor_documento'
            );

            $table->index(['orden_compra_id', 'estado']);
            $table->index(['fecha_emision', 'fecha_vencimiento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas_proveedor');
    }
};
