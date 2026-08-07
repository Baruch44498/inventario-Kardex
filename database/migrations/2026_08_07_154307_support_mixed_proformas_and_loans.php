<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proforma_detalles', function (Blueprint $table): void {
            $table->index('proforma_id', 'idx_proforma_detalle_proforma_id');
        });

        Schema::table('proforma_detalles', function (Blueprint $table): void {
            $table->dropUnique('uq_proforma_producto');
            $table->string('tratamiento', 20)
                ->default('VENTA')
                ->after('cantidad');
            $table->unique(
                ['proforma_id', 'producto_id', 'tratamiento'],
                'uq_proforma_producto_tratamiento'
            );
            $table->index(
                ['proforma_id', 'tratamiento'],
                'idx_proforma_detalle_tratamiento'
            );
        });

        Schema::create('proforma_prestamo_reposiciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proforma_detalle_id')
                ->constrained('proforma_detalles')
                ->cascadeOnDelete();
            $table->foreignId('nota_ingreso_id')
                ->nullable()
                ->constrained('notas_ingreso')
                ->nullOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->string('observacion', 300)->nullable();
            $table->foreignId('registrado_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('registrado_en');
            $table->timestamps();

            $table->index(
                ['proforma_detalle_id', 'registrado_en'],
                'idx_prestamo_reposicion_detalle_fecha'
            );
        });

        // 17.0.5: las cotizaciones nacidas de Proforma ya no generan OV.
        // Conservamos intactas las cotizaciones históricas que sí alcanzaron
        // a originar una orden antes de este cambio.
        DB::table('cotizaciones_cliente')
            ->whereNotNull('proforma_id')
            ->whereNull('orden_operacion_id')
            ->update(['tipo_orden_id' => null]);

        DB::table('roles')
            ->where('codigo', 'COMERCIAL_LOGISTICA')
            ->update([
                'descripcion' => 'Clientes, proveedores, compras, cotizaciones y órdenes OM, OS y OP.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_prestamo_reposiciones');

        Schema::table('proforma_detalles', function (Blueprint $table): void {
            $table->dropIndex('idx_proforma_detalle_tratamiento');
            $table->dropUnique('uq_proforma_producto_tratamiento');
            $table->dropColumn('tratamiento');
            $table->unique(
                ['proforma_id', 'producto_id'],
                'uq_proforma_producto'
            );
            $table->dropIndex('idx_proforma_detalle_proforma_id');
        });
    }
};
