<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_cliente_detalles', function (Blueprint $table): void {
            $table->dropForeign(['cotizacion_cliente_id']);
            $table->foreign('cotizacion_cliente_id')
                ->references('id')
                ->on('cotizaciones_cliente')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_cliente_detalles', function (Blueprint $table): void {
            $table->dropForeign(['cotizacion_cliente_id']);
            $table->foreign('cotizacion_cliente_id')
                ->references('id')
                ->on('cotizaciones_cliente')
                ->restrictOnDelete();
        });
    }
};
