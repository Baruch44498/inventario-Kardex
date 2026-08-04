<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->cambiarNulabilidadRequisicion(true);

        if (! Schema::hasColumn('cotizaciones', 'anulado_por')) {
            Schema::table('cotizaciones', function (Blueprint $table): void {
                $table->foreignId('anulado_por')
                    ->nullable()
                    ->after('evaluado_en')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('cotizaciones', 'anulado_en')) {
            Schema::table('cotizaciones', function (Blueprint $table): void {
                $table->timestamp('anulado_en')
                    ->nullable()
                    ->after('anulado_por');
            });
        }

        if (! Schema::hasColumn('cotizaciones', 'motivo_anulacion')) {
            Schema::table('cotizaciones', function (Blueprint $table): void {
                $table->string('motivo_anulacion', 500)
                    ->nullable()
                    ->after('anulado_en');
            });
        }
    }

    public function down(): void
    {
        if (DB::table('cotizaciones')->whereNull('requisicion_id')->exists()) {
            throw new \RuntimeException(
                'No se puede revertir mientras existan cotizaciones sin requisición vinculada.'
            );
        }

        if (Schema::hasColumn('cotizaciones', 'anulado_por')) {
            Schema::table('cotizaciones', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('anulado_por');
            });
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('cotizaciones', 'anulado_en') ? 'anulado_en' : null,
            Schema::hasColumn('cotizaciones', 'motivo_anulacion') ? 'motivo_anulacion' : null,
        ]));

        if ($columns !== []) {
            Schema::table('cotizaciones', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }

        $this->cambiarNulabilidadRequisicion(false);
    }

    private function cambiarNulabilidadRequisicion(bool $nullable): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $nullSql = $nullable ? 'NULL' : 'NOT NULL';

            DB::statement(
                "ALTER TABLE `cotizaciones` MODIFY `requisicion_id` BIGINT UNSIGNED {$nullSql}"
            );

            return;
        }

        Schema::table('cotizaciones', function (Blueprint $table) use ($nullable): void {
            $column = $table->unsignedBigInteger('requisicion_id');

            $nullable ? $column->nullable()->change() : $column->nullable(false)->change();
        });
    }
};
