<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_presupuestos', function (Blueprint $table): void {
            $table->string('grupo_costo', 150)->nullable()->after('tipo_costo');
            $table->decimal('margen_porcentaje', 7, 4)->default(0)->after('costo_unitario');
            $table->decimal('igv_venta_porcentaje', 7, 4)->default(18)->after('igv_porcentaje');

            foreach (['original', 'soles', 'dolares'] as $moneda) {
                $table->decimal("precio_venta_neto_{$moneda}", 20, 4)->default(0);
                $table->decimal("igv_venta_{$moneda}", 20, 4)->default(0);
                $table->decimal("precio_venta_total_{$moneda}", 20, 4)->default(0);
                $table->decimal("utilidad_estimada_{$moneda}", 20, 4)->default(0);
                $table->decimal("igv_por_pagar_{$moneda}", 20, 4)->default(0);
            }
        });

        DB::table('cotizacion_presupuestos')->update([
            'precio_venta_neto_original' => DB::raw('costo_neto_original'),
            'igv_venta_original' => DB::raw('ROUND(costo_neto_original * 0.18, 4)'),
            'precio_venta_total_original' => DB::raw('ROUND(costo_neto_original * 1.18, 4)'),
            'igv_por_pagar_original' => DB::raw('ROUND((costo_neto_original * 0.18) - igv_original, 4)'),
            'precio_venta_neto_soles' => DB::raw('costo_neto_soles'),
            'igv_venta_soles' => DB::raw('ROUND(costo_neto_soles * 0.18, 4)'),
            'precio_venta_total_soles' => DB::raw('ROUND(costo_neto_soles * 1.18, 4)'),
            'igv_por_pagar_soles' => DB::raw('ROUND((costo_neto_soles * 0.18) - igv_soles, 4)'),
            'precio_venta_neto_dolares' => DB::raw('costo_neto_dolares'),
            'igv_venta_dolares' => DB::raw('ROUND(costo_neto_dolares * 0.18, 4)'),
            'precio_venta_total_dolares' => DB::raw('ROUND(costo_neto_dolares * 1.18, 4)'),
            'igv_por_pagar_dolares' => DB::raw('ROUND((costo_neto_dolares * 0.18) - igv_dolares, 4)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('cotizacion_presupuestos', function (Blueprint $table): void {
            $table->dropColumn([
                'grupo_costo',
                'margen_porcentaje',
                'igv_venta_porcentaje',
                'precio_venta_neto_original',
                'igv_venta_original',
                'precio_venta_total_original',
                'utilidad_estimada_original',
                'igv_por_pagar_original',
                'precio_venta_neto_soles',
                'igv_venta_soles',
                'precio_venta_total_soles',
                'utilidad_estimada_soles',
                'igv_por_pagar_soles',
                'precio_venta_neto_dolares',
                'igv_venta_dolares',
                'precio_venta_total_dolares',
                'utilidad_estimada_dolares',
                'igv_por_pagar_dolares',
            ]);
        });
    }
};
