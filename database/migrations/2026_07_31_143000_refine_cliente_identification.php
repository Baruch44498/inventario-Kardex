<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            if (! Schema::hasColumn('clientes', 'numero_documento')) {
                $table->string('numero_documento', 12)
                    ->nullable()
                    ->unique()
                    ->after('tipo_documento');
            }

            if (! Schema::hasColumn('clientes', 'nombres')) {
                $table->string('nombres', 250)
                    ->nullable()
                    ->after('razon_social');
            }

            if (! Schema::hasColumn('clientes', 'apellido_paterno')) {
                $table->string('apellido_paterno', 100)
                    ->nullable()
                    ->after('nombres');
            }

            if (! Schema::hasColumn('clientes', 'apellido_materno')) {
                $table->string('apellido_materno', 100)
                    ->nullable()
                    ->after('apellido_paterno');
            }
        });

        /*
         * Conserva los RUC registrados previamente.
         * La columna histórica `ruc` permanece por compatibilidad;
         * los documentos nuevos usan `numero_documento`.
         */
        DB::table('clientes')
            ->whereNull('numero_documento')
            ->whereNotNull('ruc')
            ->update([
                'numero_documento' => DB::raw('ruc'),
                'tipo_documento' => 'RUC',
            ]);
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            if (Schema::hasColumn('clientes', 'numero_documento')) {
                $table->dropUnique('clientes_numero_documento_unique');
                $table->dropColumn('numero_documento');
            }

            $columnas = array_values(array_filter([
                Schema::hasColumn('clientes', 'nombres')
                    ? 'nombres'
                    : null,
                Schema::hasColumn('clientes', 'apellido_paterno')
                    ? 'apellido_paterno'
                    : null,
                Schema::hasColumn('clientes', 'apellido_materno')
                    ? 'apellido_materno'
                    : null,
            ]));

            if ($columnas !== []) {
                $table->dropColumn($columnas);
            }
        });
    }
};
