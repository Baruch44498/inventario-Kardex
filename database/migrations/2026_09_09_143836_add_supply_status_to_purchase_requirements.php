<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisiciones', function (Blueprint $table): void {
            $table->string('estado_abastecimiento', 20)
                ->default('PENDIENTE')
                ->after('estado');
            $table->timestamp('abastecido_en')
                ->nullable()
                ->after('atendido_en');
            $table->index(
                ['estado_abastecimiento', 'fecha_solicitud'],
                'idx_req_estado_abastecimiento'
            );
        });

        DB::table('requisiciones')
            ->orderBy('id')
            ->chunkById(200, function ($requerimientos): void {
                foreach ($requerimientos as $requerimiento) {
                    $resumen = DB::table('requisicion_detalles')
                        ->where('requisicion_id', $requerimiento->id)
                        ->selectRaw('COUNT(*) as total')
                        ->selectRaw('SUM(CASE WHEN cantidad_atendida > 0 THEN 1 ELSE 0 END) as con_recepcion')
                        ->selectRaw('SUM(CASE WHEN cantidad_atendida + 0.0001 >= cantidad_solicitada THEN 1 ELSE 0 END) as completas')
                        ->first();

                    $total = (int) ($resumen->total ?? 0);
                    $completas = (int) ($resumen->completas ?? 0);
                    $conRecepcion = (int) ($resumen->con_recepcion ?? 0);
                    $estado = $total > 0 && $completas === $total
                        ? 'COMPLETO'
                        : ($conRecepcion > 0 ? 'PARCIAL' : 'PENDIENTE');

                    DB::table('requisiciones')
                        ->where('id', $requerimiento->id)
                        ->update([
                            'estado_abastecimiento' => $estado,
                            'abastecido_en' => $estado === 'COMPLETO' ? now() : null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('requisiciones', function (Blueprint $table): void {
            $table->dropIndex('idx_req_estado_abastecimiento');
            $table->dropColumn(['estado_abastecimiento', 'abastecido_en']);
        });
    }
};
