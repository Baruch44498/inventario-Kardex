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
    {Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('ruc', 11)->nullable()->unique();
            $table->string('razon_social', 250);
            $table->string('correo', 150)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('contacto', 150)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->index('razon_social');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
