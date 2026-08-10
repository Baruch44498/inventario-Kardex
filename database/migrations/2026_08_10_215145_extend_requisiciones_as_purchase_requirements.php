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
            if (! Schema::hasColumn('requisiciones', 'origen')) {
                $table->string('origen', 30)->default('REPOSICION')->after('fecha_solicitud');
            }

            if (! Schema::hasColumn('requisiciones', 'enviado_por')) {
                $table->unsignedBigInteger('enviado_por')->nullable()->after('solicitado_por');
                $table->foreign('enviado_por', 'fk_req_enviado_por')
                    ->references('id')->on('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('requisiciones', 'enviado_en')) {
                $table->timestamp('enviado_en')->nullable()->after('enviado_por');
            }

            if (! Schema::hasColumn('requisiciones', 'recibido_por')) {
                $table->unsignedBigInteger('recibido_por')->nullable()->after('enviado_en');
                $table->foreign('recibido_por', 'fk_req_recibido_por')
                    ->references('id')->on('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('requisiciones', 'recibido_en')) {
                $table->timestamp('recibido_en')->nullable()->after('recibido_por');
            }

            if (! Schema::hasColumn('requisiciones', 'atendido_por')) {
                $table->unsignedBigInteger('atendido_por')->nullable()->after('recibido_en');
                $table->foreign('atendido_por', 'fk_req_atendido_por')
                    ->references('id')->on('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('requisiciones', 'atendido_en')) {
                $table->timestamp('atendido_en')->nullable()->after('atendido_por');
            }
        });

        Schema::table('requisicion_detalles', function (Blueprint $table): void {
            if (! Schema::hasColumn('requisicion_detalles', 'cantidad_sugerida')) {
                $table->decimal('cantidad_sugerida', 14, 3)->nullable()->after('cantidad_solicitada');
            }

            if (! Schema::hasColumn('requisicion_detalles', 'stock_fisico_snapshot')) {
                $table->decimal('stock_fisico_snapshot', 14, 3)->nullable()->after('cantidad_atendida');
            }

            if (! Schema::hasColumn('requisicion_detalles', 'reservado_snapshot')) {
                $table->decimal('reservado_snapshot', 14, 3)->nullable()->after('stock_fisico_snapshot');
            }

            if (! Schema::hasColumn('requisicion_detalles', 'disponible_snapshot')) {
                $table->decimal('disponible_snapshot', 14, 3)->nullable()->after('reservado_snapshot');
            }

            if (! Schema::hasColumn('requisicion_detalles', 'stock_minimo_snapshot')) {
                $table->decimal('stock_minimo_snapshot', 14, 3)->nullable()->after('disponible_snapshot');
            }
        });

        DB::table('requisiciones')
            ->whereNotNull('orden_operacion_id')
            ->update(['origen' => 'ORDEN_OPERACION']);

        DB::table('requisiciones')
            ->whereNull('orden_operacion_id')
            ->update(['origen' => 'REPOSICION']);
    }

    public function down(): void
    {
        Schema::table('requisicion_detalles', function (Blueprint $table): void {
            foreach (
                [
                    'cantidad_sugerida',
                    'stock_fisico_snapshot',
                    'reservado_snapshot',
                    'disponible_snapshot',
                    'stock_minimo_snapshot',
                ] as $column
            ) {
                if (Schema::hasColumn('requisicion_detalles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('requisiciones', function (Blueprint $table): void {
            foreach (
                [
                    ['fk_req_atendido_por', 'atendido_por'],
                    ['fk_req_recibido_por', 'recibido_por'],
                    ['fk_req_enviado_por', 'enviado_por'],
                ] as [$foreign, $column]
            ) {
                if (Schema::hasColumn('requisiciones', $column)) {
                    $table->dropForeign($foreign);
                }
            }

            foreach (
                [
                    'atendido_en',
                    'atendido_por',
                    'recibido_en',
                    'recibido_por',
                    'enviado_en',
                    'enviado_por',
                    'origen',
                ] as $column
            ) {
                if (Schema::hasColumn('requisiciones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
