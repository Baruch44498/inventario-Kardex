<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 15)->unique();
            $table->string('nombre', 80);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_medida');
    }
};
