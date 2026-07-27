<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_medida_id')
                ->constrained('unidades_medida')
                ->restrictOnDelete();
            $table->foreignId('marca_principal_id')
                ->nullable()
                ->constrained('marcas')
                ->nullOnDelete();
            $table->string('codigo', 50)->unique();
            $table->string('descripcion', 500);
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->index('descripcion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
