<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            if (! Schema::hasColumn('cotizaciones', 'total_calculado')) {
                $table->decimal('total_calculado', 14, 4)->default(0)->after('total');
            }
            if (! Schema::hasColumn('cotizaciones', 'ajuste_redondeo')) {
                $table->decimal('ajuste_redondeo', 8, 4)->default(0)->after('total_calculado');
            }
            if (! Schema::hasColumn('cotizaciones', 'moneda_documento')) {
                $table->string('moneda_documento', 3)->nullable()->after('ajuste_redondeo');
            }
            if (! Schema::hasColumn('cotizaciones', 'subtotal_documento')) {
                $table->decimal('subtotal_documento', 14, 4)->nullable()->after('moneda_documento');
            }
            if (! Schema::hasColumn('cotizaciones', 'impuesto_documento')) {
                $table->decimal('impuesto_documento', 14, 4)->nullable()->after('subtotal_documento');
            }
            if (! Schema::hasColumn('cotizaciones', 'total_documento')) {
                $table->decimal('total_documento', 14, 4)->nullable()->after('impuesto_documento');
            }
            if (! Schema::hasColumn('cotizaciones', 'ajuste_redondeo_motivo')) {
                $table->string('ajuste_redondeo_motivo', 255)->nullable()->after('total_documento');
            }
            if (! Schema::hasColumn('cotizaciones', 'ajuste_redondeo_confirmado_por')) {
                $table->foreignId('ajuste_redondeo_confirmado_por')
                    ->nullable()
                    ->after('ajuste_redondeo_motivo')
                    ->constrained('users', indexName: 'fk_cot_ajuste_confirma')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('cotizaciones', 'ajuste_redondeo_confirmado_en')) {
                $table->timestamp('ajuste_redondeo_confirmado_en')
                    ->nullable()
                    ->after('ajuste_redondeo_confirmado_por');
            }
        });

        DB::table('cotizaciones')->update([
            'total_calculado' => DB::raw('total'),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('cotizaciones', 'ajuste_redondeo_confirmado_por')) {
            Schema::table('cotizaciones', function (Blueprint $table): void {
                $table->dropForeign('fk_cot_ajuste_confirma');
            });
        }

        $columnas = collect([
            'total_calculado',
            'ajuste_redondeo',
            'moneda_documento',
            'subtotal_documento',
            'impuesto_documento',
            'total_documento',
            'ajuste_redondeo_motivo',
            'ajuste_redondeo_confirmado_por',
            'ajuste_redondeo_confirmado_en',
        ])->filter(fn(string $columna): bool => Schema::hasColumn('cotizaciones', $columna))->all();

        if ($columnas !== []) {
            Schema::table('cotizaciones', function (Blueprint $table) use ($columnas): void {
                $table->dropColumn($columnas);
            });
        }
    }
};
