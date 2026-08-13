<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_detalles', function (Blueprint $table): void {
            $table->string('tipo_vinculacion', 20)
                ->nullable()
                ->after('requisicion_detalle_id');
            $table->string('vinculacion_origen', 20)
                ->nullable()
                ->after('tipo_vinculacion');
            $table->string('codigo_documento', 120)
                ->nullable()
                ->after('vinculacion_origen');
            $table->string('descripcion_documento', 500)
                ->nullable()
                ->after('codigo_documento');
            $table->index('tipo_vinculacion', 'idx_cot_det_tipo_vinculacion');
        });

        // Los registros históricos solo podían relacionar el mismo producto
        // del requerimiento. Se clasifican sin alterar la relación existente.
        DB::table('cotizacion_detalles')
            ->whereNull('requisicion_detalle_id')
            ->update([
                'tipo_vinculacion' => 'ADICIONAL',
                'vinculacion_origen' => 'LEGADO',
            ]);

        DB::table('cotizacion_detalles')
            ->whereNotNull('requisicion_detalle_id')
            ->update([
                'tipo_vinculacion' => 'SOLICITADO',
                'vinculacion_origen' => 'LEGADO',
            ]);
    }

    public function down(): void
    {
        Schema::table('cotizacion_detalles', function (Blueprint $table): void {
            $table->dropIndex('idx_cot_det_tipo_vinculacion');
            $table->dropColumn([
                'tipo_vinculacion',
                'vinculacion_origen',
                'codigo_documento',
                'descripcion_documento',
            ]);
        });
    }
};
