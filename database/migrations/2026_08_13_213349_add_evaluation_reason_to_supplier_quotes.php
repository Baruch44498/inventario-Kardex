<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->string('motivo_evaluacion', 500)
                ->nullable()
                ->after('evaluado_en');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->dropColumn('motivo_evaluacion');
        });
    }
};
