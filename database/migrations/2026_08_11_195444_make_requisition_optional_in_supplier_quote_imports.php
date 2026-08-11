<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('importaciones_cotizacion_proveedor')) {
            return;
        }

        Schema::table('importaciones_cotizacion_proveedor', function (Blueprint $table): void {
            $this->dropRequisicionForeign($table);
        });

        Schema::table('importaciones_cotizacion_proveedor', function (Blueprint $table): void {
            $table->unsignedBigInteger('requisicion_id')->nullable()->change();
        });

        Schema::table('importaciones_cotizacion_proveedor', function (Blueprint $table): void {
            $table->foreign('requisicion_id', 'fk_imp_cot_req')
                ->references('id')
                ->on('requisiciones')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('importaciones_cotizacion_proveedor')) {
            return;
        }

        Schema::table('importaciones_cotizacion_proveedor', function (Blueprint $table): void {
            $this->dropRequisicionForeign($table);
        });

        Schema::table('importaciones_cotizacion_proveedor', function (Blueprint $table): void {
            $table->unsignedBigInteger('requisicion_id')->nullable(false)->change();
        });

        Schema::table('importaciones_cotizacion_proveedor', function (Blueprint $table): void {
            $table->foreign('requisicion_id', 'fk_imp_cot_req')
                ->references('id')
                ->on('requisiciones')
                ->restrictOnDelete();
        });
    }

    /**
     * SQLite reconstruye la tabla y necesita conocer la columna de la FK.
     * MySQL/MariaDB, en cambio, necesita el nombre personalizado real.
     */
    private function dropRequisicionForeign(Blueprint $table): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $table->dropForeign(['requisicion_id']);

            return;
        }

        $table->dropForeign('fk_imp_cot_req');
    }
};
