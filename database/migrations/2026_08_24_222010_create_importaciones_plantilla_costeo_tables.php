<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importaciones_plantilla_costeo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tipo_orden_id')
                ->constrained('tipos_orden')
                ->restrictOnDelete();
            $table->string('nombre', 180);
            $table->string('descripcion', 500)->nullable();
            $table->string('hoja', 180)->nullable();
            $table->string('nombre_original', 255);
            $table->string('ruta_archivo', 500);
            $table->string('mime_type', 150)->nullable();
            $table->json('advertencias')->nullable();
            $table->string('estado', 20)->default('BORRADOR');
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmado_en')->nullable();
            $table->timestamps();

            $table->index(['estado', 'creado_por']);
        });

        Schema::create('importacion_plantilla_costeo_partidas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('importacion_id')
                ->constrained('importaciones_plantilla_costeo')
                ->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->unsignedInteger('fila_excel');
            $table->string('grupo_costo', 150)->nullable();
            $table->string('codigo_referencia', 80)->nullable();
            $table->string('descripcion', 300);
            $table->decimal('cantidad', 14, 3);
            $table->string('unidad_original', 50)->nullable();
            $table->string('tipo_costo', 40);
            $table->string('unidad', 20);
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 14, 6);
            $table->decimal('costo_unitario', 14, 4);
            $table->decimal('margen_porcentaje', 7, 4)->default(0);
            $table->decimal('carga_social_porcentaje', 7, 4)->default(0);
            $table->string('igv_modo', 20)->default('INCLUIDO');
            $table->decimal('igv_porcentaje', 7, 4)->default(18);
            $table->decimal('igv_venta_porcentaje', 7, 4)->default(18);
            $table->string('estado_vinculacion', 20)->default('PENDIENTE');
            $table->boolean('omitida')->default(false);
            $table->string('observacion', 500)->nullable();
            $table->unsignedInteger('orden_secuencia');
            $table->timestamps();

            $table->unique(['importacion_id', 'fila_excel'], 'uq_importacion_plantilla_fila');
            $table->index(['importacion_id', 'omitida', 'estado_vinculacion'], 'idx_importacion_plantilla_revision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacion_plantilla_costeo_partidas');
        Schema::dropIfExists('importaciones_plantilla_costeo');
    }
};
