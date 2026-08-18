<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_operacion', function (Blueprint $table): void {
            $table->foreignId('cerrado_por')
                ->nullable()
                ->after('cerrado_en')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('observacion_cierre', 500)
                ->nullable()
                ->after('cerrado_por');
            $table->decimal('ingreso_neto_cierre_soles', 14, 4)
                ->nullable()
                ->after('observacion_cierre');
            $table->decimal('costo_real_cierre_soles', 14, 4)
                ->nullable()
                ->after('ingreso_neto_cierre_soles');
            $table->decimal('utilidad_real_cierre_soles', 14, 4)
                ->nullable()
                ->after('costo_real_cierre_soles');
            $table->decimal('margen_real_cierre_porcentaje', 9, 4)
                ->nullable()
                ->after('utilidad_real_cierre_soles');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_operacion', function (Blueprint $table): void {
            $table->dropForeign(['cerrado_por']);
            $table->dropColumn([
                'cerrado_por',
                'observacion_cierre',
                'ingreso_neto_cierre_soles',
                'costo_real_cierre_soles',
                'utilidad_real_cierre_soles',
                'margen_real_cierre_porcentaje',
            ]);
        });
    }
};
