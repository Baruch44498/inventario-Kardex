<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_eventos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entidad', 60);
            $table->unsignedBigInteger('entidad_id');
            $table->string('etiqueta', 100)->nullable();
            $table->string('accion', 20);
            $table->json('campos')->nullable();
            $table->string('estado_anterior', 50)->nullable();
            $table->string('estado_nuevo', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entidad', 'entidad_id', 'created_at']);
            $table->index(['accion', 'created_at']);
            $table->index(['usuario_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_eventos');
    }
};
