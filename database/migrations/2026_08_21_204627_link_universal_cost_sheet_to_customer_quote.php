<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones_cliente', function (Blueprint $table): void {
            $table->timestamp('costeo_sincronizado_en')
                ->nullable()
                ->after('total');
        });

        Schema::table('cotizacion_cliente_detalles', function (Blueprint $table): void {
            $table->string('tipo_linea', 20)->default('PRODUCTO')->after('producto_id');
            $table->boolean('origen_costeo')->default(false)->after('tipo_linea');
            $table->index(
                ['cotizacion_cliente_id', 'origen_costeo'],
                'idx_cotizacion_detalle_origen_costeo'
            );
        });

        Schema::table('cotizacion_cliente_detalles', function (Blueprint $table): void {
            $table->dropForeign(['producto_id']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `cotizacion_cliente_detalles` '
                    . 'MODIFY `producto_id` BIGINT UNSIGNED NULL'
            );
        } else {
            Schema::table('cotizacion_cliente_detalles', function (Blueprint $table): void {
                $table->unsignedBigInteger('producto_id')->nullable()->change();
            });
        }

        Schema::table('cotizacion_cliente_detalles', function (Blueprint $table): void {
            $table->foreign('producto_id')
                ->references('id')
                ->on('productos')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('cotizacion_cliente_detalles')->whereNull('producto_id')->exists()) {
            throw new RuntimeException(
                'No se puede revertir mientras existan líneas comerciales resumidas sin producto.'
            );
        }

        Schema::table('cotizacion_cliente_detalles', function (Blueprint $table): void {
            $table->dropForeign(['producto_id']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `cotizacion_cliente_detalles` '
                    . 'MODIFY `producto_id` BIGINT UNSIGNED NOT NULL'
            );
        } else {
            Schema::table('cotizacion_cliente_detalles', function (Blueprint $table): void {
                $table->unsignedBigInteger('producto_id')->nullable(false)->change();
            });
        }

        Schema::table('cotizacion_cliente_detalles', function (Blueprint $table): void {
            $table->foreign('producto_id')
                ->references('id')
                ->on('productos')
                ->restrictOnDelete();
        });

        Schema::table('cotizaciones_cliente', function (Blueprint $table): void {
            $table->dropColumn('costeo_sincronizado_en');
        });

        Schema::table('cotizacion_cliente_detalles', function (Blueprint $table): void {
            $table->dropIndex('idx_cotizacion_detalle_origen_costeo');
            $table->dropColumn(['tipo_linea', 'origen_costeo']);
        });
    }
};
