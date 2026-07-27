<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repisas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('descripcion', 180)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repisas');
    }
};
