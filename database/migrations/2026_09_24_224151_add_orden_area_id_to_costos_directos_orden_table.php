<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_directos_orden', function (Blueprint $table): void {
            $table->foreignId('orden_area_id')->nullable()->after('orden_operacion_id')
                ->constrained('orden_areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('costos_directos_orden', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('orden_area_id');
        });
    }
};
