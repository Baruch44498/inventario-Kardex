<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_porcentajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')
                ->constrained('productos')
                ->cascadeOnDelete();
            $table->string('concepto', 60);
            $table->decimal('valor_porcentaje', 7, 4);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->boolean('estado')->default(true);
            $table->string('observacion', 255)->nullable();
            $table->timestamps();
            $table->index(
                ['producto_id', 'estado', 'fecha_inicio', 'fecha_fin'],
                'idx_producto_porcentaje_vigencia'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_porcentajes');
    }
};
