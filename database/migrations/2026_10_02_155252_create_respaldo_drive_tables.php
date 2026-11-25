<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_conexiones', function (Blueprint $table): void {
            $table->id();
            $table->text('refresh_token');
            $table->foreignId('conectado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('documentos_drive', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo', 40);
            $table->unsignedBigInteger('origen_id');
            $table->string('hash_sha256', 64);
            $table->string('estado', 20);
            $table->string('drive_id')->nullable();
            $table->string('drive_url', 600)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tipo', 'origen_id', 'hash_sha256'], 'uq_documento_drive_origen_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_drive');
        Schema::dropIfExists('drive_conexiones');
    }
};
