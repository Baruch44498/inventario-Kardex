<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_cliente', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 100);
            $table->decimal('porcentaje_ganancia', 7, 2)->default(0);
            $table->string('descripcion', 250)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        $ahora = now();

        DB::table('tipos_cliente')->insert([
            [
                'codigo' => 'FINAL',
                'nombre' => 'Cliente final',
                'porcentaje_ganancia' => 0,
                'descripcion' => 'Compra para consumo o uso directo.',
                'estado' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'codigo' => 'MAYORISTA',
                'nombre' => 'Mayorista',
                'porcentaje_ganancia' => 0,
                'descripcion' => 'Compra por volumen o para redistribución.',
                'estado' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'codigo' => 'FABRICANTE',
                'nombre' => 'Fabricante',
                'porcentaje_ganancia' => 0,
                'descripcion' => 'Cliente industrial o de fabricación.',
                'estado' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
        ]);

        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('tipo_cliente_id')
                ->nullable()
                ->after('id')
                ->constrained('tipos_cliente')
                ->restrictOnDelete();
            $table->string('tipo_documento', 20)
                ->default('RUC')
                ->after('tipo_cliente_id');
            $table->string('nombre_comercial', 250)
                ->nullable()
                ->after('razon_social');
            $table->boolean('es_mostrador')
                ->default(false)
                ->after('contacto');
            $table->index(['tipo_cliente_id', 'estado']);
            $table->index('es_mostrador');
        });

        $tipoFinalId = DB::table('tipos_cliente')
            ->where('codigo', 'FINAL')
            ->value('id');

        DB::table('clientes')
            ->whereNull('tipo_cliente_id')
            ->update(['tipo_cliente_id' => $tipoFinalId]);

        Schema::table('cliente_direcciones', function (Blueprint $table) {
            $table->string('provincia', 100)
                ->nullable()
                ->after('departamento');
            $table->string('distrito', 100)
                ->nullable()
                ->after('provincia');
            $table->index(['departamento', 'provincia', 'distrito']);
        });

        Schema::table('vehiculos', function (Blueprint $table) {
            $table->unsignedSmallInteger('anio')
                ->nullable()
                ->after('modelo');
            $table->string('color', 60)
                ->nullable()
                ->after('anio');
            $table->string('vin', 50)
                ->nullable()
                ->unique()
                ->after('color');
            $table->boolean('es_comodin')
                ->default(false)
                ->after('procedencia');
            $table->index('es_comodin');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropIndex(['es_comodin']);
            $table->dropUnique(['vin']);
            $table->dropColumn(['anio', 'color', 'vin', 'es_comodin']);
        });

        Schema::table('cliente_direcciones', function (Blueprint $table) {
            $table->dropIndex(['departamento', 'provincia', 'distrito']);
            $table->dropColumn(['provincia', 'distrito']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['tipo_cliente_id', 'estado']);
            $table->dropIndex(['es_mostrador']);
            $table->dropConstrainedForeignId('tipo_cliente_id');
            $table->dropColumn([
                'tipo_documento',
                'nombre_comercial',
                'es_mostrador',
            ]);
        });

        Schema::dropIfExists('tipos_cliente');
    }
};
