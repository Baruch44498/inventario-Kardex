<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_compra', function (Blueprint $table): void {
            $table->decimal('total_lineas', 14, 4)->default(0)->after('descripcion');
            $table->decimal('ajuste_redondeo', 14, 4)->default(0)->after('total_lineas');
            $table->decimal('total_seleccionado', 14, 4)->default(0)->after('ajuste_redondeo');
        });

        DB::table('solicitudes_compra')
            ->select(['id', 'cotizacion_id'])
            ->orderBy('id')
            ->chunkById(100, function ($solicitudes): void {
                foreach ($solicitudes as $solicitud) {
                    $totalLineas = round((float) DB::table('solicitud_compra_detalles')
                        ->where('solicitud_compra_id', $solicitud->id)
                        ->sum('subtotal'), 4);
                    $lineasSeleccionadas = DB::table('solicitud_compra_detalles')
                        ->where('solicitud_compra_id', $solicitud->id)
                        ->count();
                    $cotizacion = DB::table('cotizaciones')
                        ->where('id', $solicitud->cotizacion_id)
                        ->first(['id', 'total']);
                    $lineasCotizacion = DB::table('cotizacion_detalles')
                        ->where('cotizacion_id', $solicitud->cotizacion_id)
                        ->count();
                    $totalSeleccionado = $cotizacion && $lineasSeleccionadas === $lineasCotizacion
                        ? round((float) $cotizacion->total, 4)
                        : $totalLineas;

                    DB::table('solicitudes_compra')
                        ->where('id', $solicitud->id)
                        ->update([
                            'total_lineas' => $totalLineas,
                            'ajuste_redondeo' => round($totalSeleccionado - $totalLineas, 4),
                            'total_seleccionado' => $totalSeleccionado,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('solicitudes_compra', function (Blueprint $table): void {
            $table->dropColumn(['total_lineas', 'ajuste_redondeo', 'total_seleccionado']);
        });
    }
};
