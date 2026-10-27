<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE `repisas` MODIFY `codigo` VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // La colación anterior trataría como duplicados códigos distintos como NA001 y ÑA001.
        $colision = DB::selectOne(
            'SELECT 1 FROM `repisas` GROUP BY `codigo` COLLATE utf8mb4_unicode_ci HAVING COUNT(*) > 1 LIMIT 1'
        );

        if ($colision !== null) {
            throw new RuntimeException('No se puede restaurar utf8mb4_unicode_ci: hay códigos de repisa que colisionarían.');
        }

        DB::statement(
            'ALTER TABLE `repisas` MODIFY `codigo` VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL'
        );
    }
};
