<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_compra', function (Blueprint $table): void {
            if (! Schema::hasColumn('solicitudes_compra', 'origen')) {
                $table->string('origen', 30)->default('REQUERIMIENTO')->after('descripcion');
                $table->index('origen', 'idx_solicitudes_compra_origen');
            }

            if (! Schema::hasColumn('solicitudes_compra', 'justificacion_origen')) {
                $table->string('justificacion_origen', 500)->nullable()->after('origen');
            }
        });

        Schema::table('ordenes_compra', function (Blueprint $table): void {
            if (! Schema::hasColumn('ordenes_compra', 'origen')) {
                $table->string('origen', 30)->default('REQUERIMIENTO')->after('numero_documento_proveedor');
                $table->index('origen', 'idx_ordenes_compra_origen');
            }

            if (! Schema::hasColumn('ordenes_compra', 'justificacion_origen')) {
                $table->string('justificacion_origen', 500)->nullable()->after('origen');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table): void {
            if (Schema::hasColumn('ordenes_compra', 'origen')) {
                $table->dropIndex('idx_ordenes_compra_origen');
            }

            $columnas = collect(['origen', 'justificacion_origen'])
                ->filter(fn(string $columna): bool => Schema::hasColumn('ordenes_compra', $columna))
                ->all();

            if ($columnas !== []) {
                $table->dropColumn($columnas);
            }
        });

        Schema::table('solicitudes_compra', function (Blueprint $table): void {
            if (Schema::hasColumn('solicitudes_compra', 'origen')) {
                $table->dropIndex('idx_solicitudes_compra_origen');
            }

            $columnas = collect(['origen', 'justificacion_origen'])
                ->filter(fn(string $columna): bool => Schema::hasColumn('solicitudes_compra', $columna))
                ->all();

            if ($columnas !== []) {
                $table->dropColumn($columnas);
            }
        });
    }
};
