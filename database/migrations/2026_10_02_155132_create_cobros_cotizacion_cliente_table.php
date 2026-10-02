<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobros_cotizacion_cliente', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cotizacion_cliente_id')->constrained('cotizaciones_cliente')->restrictOnDelete();
            $table->date('fecha_cobro');
            $table->decimal('monto', 14, 4);
            $table->string('medio_cobro', 30);
            $table->string('referencia', 100)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index(['cotizacion_cliente_id', 'anulado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobros_cotizacion_cliente');
    }
};
