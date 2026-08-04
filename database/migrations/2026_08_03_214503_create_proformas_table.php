<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proformas', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();

            $table->string('codigo', 40)->unique();
            $table->date('fecha_emision');
            $table->date('fecha_validez')->nullable();
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 14, 6)->nullable();

            // Copia histórica del margen del tipo de cliente al cotizar.
            $table->decimal('margen_cliente_porcentaje', 7, 4)->default(0);

            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('impuesto', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->string('condiciones_pago', 500)->nullable();
            $table->string('condiciones_entrega', 500)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->string('estado', 20)->default('BORRADOR');

            $table->foreignId('registrado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('emitido_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('emitido_en')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'fecha_emision']);
            $table->index(['estado', 'fecha_emision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proformas');
    }
};
