<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_proveedor', function (Blueprint $table): void {
            if (! Schema::hasColumn('facturas_proveedor', 'ajuste_redondeo')) {
                $table->decimal('ajuste_redondeo', 14, 4)->default(0)->after('total');
            }
            if (! Schema::hasColumn('facturas_proveedor', 'archivo_original_path')) {
                $table->string('archivo_original_path', 500)->nullable()->after('observacion');
                $table->string('archivo_original_nombre', 255)->nullable()->after('archivo_original_path');
                $table->string('archivo_original_mime', 100)->nullable()->after('archivo_original_nombre');
                $table->string('archivo_original_hash', 64)->nullable()->after('archivo_original_mime');
            }
        });

        Schema::table('factura_proveedor_detalles', function (Blueprint $table): void {
            if (! Schema::hasColumn('factura_proveedor_detalles', 'igv_porcentaje')) {
                $table->decimal('igv_porcentaje', 7, 4)->default(18)->after('descuento_porcentaje');
            }
            if (! Schema::hasColumn('factura_proveedor_detalles', 'impuesto')) {
                $table->decimal('impuesto', 14, 4)->default(0)->after('subtotal');
            }
            if (! Schema::hasColumn('factura_proveedor_detalles', 'total')) {
                $table->decimal('total', 14, 4)->default(0)->after('impuesto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('factura_proveedor_detalles', function (Blueprint $table): void {
            $columnas = collect(['igv_porcentaje', 'impuesto', 'total'])
                ->filter(fn(string $columna): bool => Schema::hasColumn('factura_proveedor_detalles', $columna))
                ->all();
            if ($columnas !== []) {
                $table->dropColumn($columnas);
            }
        });

        Schema::table('facturas_proveedor', function (Blueprint $table): void {
            $columnas = collect([
                'ajuste_redondeo',
                'archivo_original_path',
                'archivo_original_nombre',
                'archivo_original_mime',
                'archivo_original_hash',
            ])->filter(fn(string $columna): bool => Schema::hasColumn('facturas_proveedor', $columna))->all();
            if ($columnas !== []) {
                $table->dropColumn($columnas);
            }
        });
    }
};
