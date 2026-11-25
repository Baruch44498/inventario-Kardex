<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aliases_producto_proveedor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('codigo_proveedor', 120)->nullable();
            $table->string('descripcion_proveedor', 500)->nullable();
            $table->char('clave', 64);
            $table->foreignId('confirmado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['proveedor_id', 'clave'], 'uq_alias_producto_proveedor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aliases_producto_proveedor');
    }
};
