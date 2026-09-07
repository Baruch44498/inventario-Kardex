<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('importaciones_plantilla_costeo', function (Blueprint $table): void {
            $table->foreignId('cotizacion_cliente_id')->nullable()->constrained('cotizaciones_cliente')->restrictOnDelete();
            $table->string('archivo_sha256', 64)->nullable();
            $table->unique(['cotizacion_cliente_id', 'archivo_sha256'], 'uq_importacion_cotizacion_archivo');
        });
        Schema::table('importacion_plantilla_costeo_partidas', function (Blueprint $table): void {
            $table->json('ruta_areas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('importacion_plantilla_costeo_partidas', fn(Blueprint $table) => $table->dropColumn('ruta_areas'));
        Schema::table('importaciones_plantilla_costeo', function (Blueprint $table): void {
            $table->dropUnique('uq_importacion_cotizacion_archivo');
            $table->dropConstrainedForeignId('cotizacion_cliente_id');
            $table->dropColumn('archivo_sha256');
        });
    }
};
