<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table): void {
            $table->decimal('ajuste_redondeo', 14, 4)->default(0)->after('impuesto');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table): void {
            $table->dropColumn('ajuste_redondeo');
        });
    }
};
