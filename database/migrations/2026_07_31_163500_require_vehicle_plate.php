<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $vehiculosSinPlaca = DB::table('vehiculos')
            ->where(function ($query): void {
                $query
                    ->whereNull('placa')
                    ->orWhereRaw("TRIM(placa) = ''");
            })
            ->orderBy('id')
            ->get();

        foreach ($vehiculosSinPlaca as $vehiculo) {
            $placaMigrada = trim((string) ($vehiculo->codigo_interno ?? ''));

            if (
                $placaMigrada === ''
                && (bool) ($vehiculo->es_comodin ?? false)
            ) {
                $placaMigrada = 'SIN-PLACA';
            }

            if ($placaMigrada === '') {
                throw new \RuntimeException(
                    "El vehículo ID {$vehiculo->id} no tiene placa. "
                        . 'Registra una placa antes de continuar la migración.'
                );
            }

            if (mb_strlen($placaMigrada) > 20) {
                throw new \RuntimeException(
                    "El identificador del vehículo ID {$vehiculo->id} "
                        . 'supera los 20 caracteres permitidos para la placa.'
                );
            }

            $placaMigrada = mb_strtoupper($placaMigrada);

            $duplicada = DB::table('vehiculos')
                ->where('placa', $placaMigrada)
                ->where('id', '!=', $vehiculo->id)
                ->exists();

            if ($duplicada) {
                throw new \RuntimeException(
                    "No se puede migrar el vehículo ID {$vehiculo->id}: "
                        . "la placa {$placaMigrada} ya está registrada."
                );
            }

            DB::table('vehiculos')
                ->where('id', $vehiculo->id)
                ->update(['placa' => $placaMigrada]);
        }

        $this->cambiarNulabilidadPlaca(false);
    }

    public function down(): void
    {
        $this->cambiarNulabilidadPlaca(true);
    }

    private function cambiarNulabilidadPlaca(bool $nullable): void
    {
        /*
         * En MySQL/MariaDB, change() intentaba recrear el índice único ya
         * existente y fallaba con "Duplicate key name vehiculos_placa_unique".
         * MODIFY cambia solo la nulabilidad y conserva el índice actual.
         */
        if (DB::connection()->getDriverName() === 'mysql') {
            $nullSql = $nullable ? 'NULL' : 'NOT NULL';

            DB::statement(
                "ALTER TABLE `vehiculos` MODIFY `placa` VARCHAR(20) {$nullSql}"
            );

            return;
        }

        Schema::table('vehiculos', function (Blueprint $table) use ($nullable): void {
            $column = $table->string('placa', 20);

            $nullable ? $column->nullable()->change() : $column->nullable(false)->change();
        });
    }
};
