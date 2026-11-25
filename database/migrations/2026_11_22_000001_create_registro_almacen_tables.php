<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operaciones_almacen', function (Blueprint $table): void {
            $table->id();
            $table->uuid('solicitud')->unique();
            $table->string('tipo', 20);
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('origen_id')->nullable()->constrained('repisas')->restrictOnDelete();
            $table->foreignId('destino_id')->constrained('repisas')->restrictOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->decimal('costo_unitario_soles', 14, 4)->nullable();
            $table->string('observacion', 240);
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['producto_id', 'id']);
        });

        Schema::create('precios_referencia_almacen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->decimal('precio_soles', 14, 4);
            $table->string('igv_modo', 20);
            $table->date('fecha_referencia');
            $table->string('fuente', 150);
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['producto_id', 'fecha_referencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_referencia_almacen');
        Schema::dropIfExists('operaciones_almacen');
    }
};
