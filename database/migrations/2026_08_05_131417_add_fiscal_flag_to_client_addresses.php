<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente_direcciones', function (Blueprint $table) {
            $table->boolean('es_fiscal')
                ->default(false)
                ->after('referencia');
            $table->index(
                ['cliente_id', 'es_fiscal', 'estado'],
                'cliente_direcciones_fiscal_activa_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('cliente_direcciones', function (Blueprint $table) {
            $table->dropIndex('cliente_direcciones_fiscal_activa_index');
            $table->dropColumn('es_fiscal');
        });
    }
};
