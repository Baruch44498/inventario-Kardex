<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MariaDB puede reutilizar el indice unico compuesto como soporte de
        // la FK de proforma_id. Creamos primero un indice independiente para
        // poder retirar la restriccion de version sin romper la clave foranea.
        if (! Schema::hasIndex(
            'cotizaciones_cliente',
            'idx_cotizacion_cliente_proforma_id'
        )) {
            Schema::table('cotizaciones_cliente', function (Blueprint $table): void {
                $table->index(
                    'proforma_id',
                    'idx_cotizacion_cliente_proforma_id'
                );
            });
        }

        Schema::table('cotizaciones_cliente', function (Blueprint $table): void {
            $table->dropUnique('uq_cotizacion_cliente_proforma_version');
        });

        $this->cambiarNulabilidadProforma(true);

        Schema::table('cotizaciones_cliente', function (Blueprint $table): void {
            $table->string('origen', 30)
                ->default('PROFORMA_ALMACEN')
                ->after('proforma_id');
            $table->unique(
                ['codigo_base', 'version'],
                'uq_cotizacion_cliente_codigo_version'
            );
            $table->unique(
                'orden_operacion_id',
                'uq_cotizacion_cliente_orden'
            );
            $table->index(['origen', 'estado']);
        });

        // El inventario mensual genera requisiciones de compra, no proformas.
        // Se conserva cualquier registro de prueba existente sin eliminarlo.
        DB::table('proformas')
            ->where('tipo_origen', 'INVENTARIO')
            ->update(['tipo_origen' => 'VENTA_DIRECTA']);

        DB::table('roles')
            ->where('codigo', 'COMERCIAL_LOGISTICA')
            ->update([
                'descripcion' => 'Clientes, proveedores, compras, cotizaciones y órdenes OM, OV, OS y OP.',
                'updated_at' => now(),
            ]);
        DB::table('roles')
            ->where('codigo', 'ALMACEN')
            ->update([
                'descripcion' => 'Inventario, ingresos, salidas, alertas y proformas de venta directa.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (DB::table('cotizaciones_cliente')->whereNull('proforma_id')->exists()) {
            throw new RuntimeException(
                'No se puede revertir mientras existan cotizaciones directas de Logística.'
            );
        }

        Schema::table('cotizaciones_cliente', function (Blueprint $table): void {
            $table->dropIndex(['origen', 'estado']);
            $table->dropUnique('uq_cotizacion_cliente_orden');
            $table->dropUnique('uq_cotizacion_cliente_codigo_version');
            $table->dropColumn('origen');
        });

        $this->cambiarNulabilidadProforma(false);

        Schema::table('cotizaciones_cliente', function (Blueprint $table): void {
            $table->unique(
                ['proforma_id', 'version'],
                'uq_cotizacion_cliente_proforma_version'
            );
        });

        if (Schema::hasIndex(
            'cotizaciones_cliente',
            'idx_cotizacion_cliente_proforma_id'
        )) {
            Schema::table('cotizaciones_cliente', function (Blueprint $table): void {
                $table->dropIndex('idx_cotizacion_cliente_proforma_id');
            });
        }
    }

    private function cambiarNulabilidadProforma(bool $nullable): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $nullSql = $nullable ? 'NULL' : 'NOT NULL';

            DB::statement(
                "ALTER TABLE `cotizaciones_cliente` MODIFY `proforma_id` BIGINT UNSIGNED {$nullSql}"
            );

            return;
        }

        Schema::table('cotizaciones_cliente', function (Blueprint $table) use ($nullable): void {
            $column = $table->unsignedBigInteger('proforma_id');

            $nullable
                ? $column->nullable()->change()
                : $column->nullable(false)->change();
        });
    }
};
