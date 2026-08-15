<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factura_proveedor_detalles', function (Blueprint $table): void {
            if (! Schema::hasColumn('factura_proveedor_detalles', 'nota_ingreso_detalle_id')) {
                $table->foreignId('nota_ingreso_detalle_id')
                    ->nullable()
                    ->after('orden_compra_detalle_id')
                    ->constrained('nota_ingreso_detalles')
                    ->restrictOnDelete();
                $table->index('nota_ingreso_detalle_id', 'idx_factura_detalle_ingreso');
            }
            if (! Schema::hasColumn('factura_proveedor_detalles', 'costo_provisional_soles')) {
                $table->decimal('costo_provisional_soles', 14, 4)->default(0)->after('total');
                $table->decimal('ajuste_inventario_soles', 14, 4)->default(0)->after('costo_provisional_soles');
                $table->decimal('diferencia_contable_soles', 14, 4)->default(0)->after('ajuste_inventario_soles');
            }
        });
    }

    public function down(): void
    {
        Schema::table('factura_proveedor_detalles', function (Blueprint $table): void {
            if (Schema::hasIndex('factura_proveedor_detalles', 'idx_factura_detalle_ingreso')) {
                $table->dropIndex('idx_factura_detalle_ingreso');
            }
            if (Schema::hasColumn('factura_proveedor_detalles', 'nota_ingreso_detalle_id')) {
                $table->dropConstrainedForeignId('nota_ingreso_detalle_id');
            }

            $columnas = collect([
                'costo_provisional_soles',
                'ajuste_inventario_soles',
                'diferencia_contable_soles',
            ])->filter(fn(string $columna): bool => Schema::hasColumn('factura_proveedor_detalles', $columna))->all();

            if ($columnas !== []) {
                $table->dropColumn($columnas);
            }
        });
    }
};
