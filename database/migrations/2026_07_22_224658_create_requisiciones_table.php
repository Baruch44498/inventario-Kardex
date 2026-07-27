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
        Schema::create('requisiciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('orden_operacion_id')
                ->nullable()
                ->constrained('ordenes_operacion')
                ->restrictOnDelete();

            $table->string('codigo', 40)->unique();
            $table->date('fecha_solicitud');
            $table->string('descripcion', 500)->nullable();
            $table->string('prioridad', 20)->default('NORMAL');
            $table->string('estado', 30)->default('BORRADOR');

            $table->foreignId('solicitado_por')
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

            $table->index(['estado', 'fecha_solicitud']);
            $table->index('orden_operacion_id');
            $table->index('solicitado_por');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisiciones');
    }
};
