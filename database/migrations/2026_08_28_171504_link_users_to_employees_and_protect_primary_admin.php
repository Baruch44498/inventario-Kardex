<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('empleado_id')
                ->nullable()
                ->after('role_id')
                ->unique()
                ->constrained('empleados')
                ->nullOnDelete();
            $table->boolean('es_administrador_principal')
                ->default(false)
                ->after('estado');
        });

        $rolAdministradorId = DB::table('roles')
            ->where('codigo', 'ADMINISTRADOR')
            ->value('id');

        if (! $rolAdministradorId) {
            return;
        }

        $administradoresActivos = DB::table('users')
            ->where('role_id', $rolAdministradorId)
            ->where('estado', true)
            ->orderBy('id')
            ->pluck('id');

        // Si existe una sola cuenta administrativa no hay ambigüedad: esa
        // cuenta actual es la del dueño. Con varias cuentas la selección se
        // realizará explícitamente desde la pantalla de Usuarios.
        if ($administradoresActivos->count() === 1) {
            DB::table('users')
                ->where('id', $administradoresActivos->first())
                ->update(['es_administrador_principal' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['empleado_id']);
            $table->dropUnique(['empleado_id']);
            $table->dropColumn([
                'empleado_id',
                'es_administrador_principal',
            ]);
        });
    }
};
