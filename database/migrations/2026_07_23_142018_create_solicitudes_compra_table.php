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
        Schema::create('solicitudes_compra', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cotizacion_id')
                ->unique()
                ->constrained('cotizaciones')
                ->restrictOnDelete();

            $table->string('codigo', 40)->unique();
            $table->date('fecha_solicitud');
            $table->string('descripcion', 500)->nullable();
            $table->string('estado', 30)->default('BORRADOR');

            $table->foreignId('solicitado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('aprobado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('aprobado_en')->nullable();

            $table->foreignId('rechazado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('rechazado_en')->nullable();
            $table->string('motivo_rechazo', 500)->nullable();

            $table->foreignId('anulado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('anulado_en')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index(['estado', 'fecha_solicitud']);
            $table->index('solicitado_por');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_compra');
    }
};
