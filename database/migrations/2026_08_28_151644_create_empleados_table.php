<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_completo', 150);
            $table->char('dni', 8)->unique();
            $table->boolean('estado')->default(true)->index();
            $table->foreignId('registrado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('actualizado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('nombre_completo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
