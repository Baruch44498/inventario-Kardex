<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_costeo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tipo_orden_id')
                ->constrained('tipos_orden')
                ->restrictOnDelete();
            $table->string('nombre', 180);
            $table->string('descripcion', 500)->nullable();
            $table->string('origen', 30)->default('HOJA_COSTOS');
            $table->boolean('activo')->default(true);
            $table->foreignId('creado_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['tipo_orden_id', 'nombre'],
                'uq_plantilla_costeo_tipo_nombre'
            );
            $table->index(['activo', 'tipo_orden_id']);
        });

        Schema::create('plantilla_costeo_partidas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plantilla_costeo_id')
                ->constrained('plantillas_costeo')
                ->cascadeOnDelete();
            $table->foreignId('producto_id')
                ->nullable()
                ->constrained('productos')
                ->nullOnDelete();
            $table->string('codigo_referencia', 80)->nullable();
            $table->string('tipo_costo', 40);
            $table->string('grupo_costo', 150)->nullable();
            $table->string('descripcion', 300);
            $table->decimal('cantidad', 14, 3);
            $table->string('unidad', 20);
            $table->string('moneda', 3);
            $table->decimal('tipo_cambio', 14, 6);
            $table->decimal('costo_unitario', 14, 4);
            $table->decimal('margen_porcentaje', 7, 4)->default(0);
            $table->decimal('carga_social_porcentaje', 7, 4)->default(0);
            $table->string('igv_modo', 20)->default('NO_APLICA');
            $table->decimal('igv_porcentaje', 7, 4)->default(18);
            $table->decimal('igv_venta_porcentaje', 7, 4)->default(18);
            $table->string('observacion', 500)->nullable();
            $table->unsignedInteger('orden_secuencia');
            $table->timestamps();

            $table->unique(
                ['plantilla_costeo_id', 'orden_secuencia'],
                'uq_plantilla_costeo_partida_secuencia'
            );
            $table->index(['plantilla_costeo_id', 'grupo_costo']);
            $table->index(['tipo_costo', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_costeo_partidas');
        Schema::dropIfExists('plantillas_costeo');
    }
};
