<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('importaciones_cotizacion_proveedor')) {
            Schema::create('importaciones_cotizacion_proveedor', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('requisicion_id')->constrained('requisiciones', indexName: 'fk_imp_cot_req')->restrictOnDelete();
                $table->foreignId('proveedor_id')->nullable()->constrained('proveedores', indexName: 'fk_imp_cot_prov')->nullOnDelete();
                $table->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones', indexName: 'fk_imp_cot_final')->nullOnDelete();
                $table->string('tipo_archivo', 12);
                $table->string('nombre_original', 255);
                $table->string('ruta_archivo', 500);
                $table->string('mime_type', 120)->nullable();
                $table->json('datos_extraidos');
                $table->json('advertencias')->nullable();
                $table->string('estado', 20)->default('BORRADOR');
                $table->foreignId('creado_por')->constrained('users', indexName: 'fk_imp_cot_creador')->restrictOnDelete();
                $table->foreignId('confirmado_por')->nullable()->constrained('users', indexName: 'fk_imp_cot_confirma')->nullOnDelete();
                $table->timestamp('confirmado_en')->nullable();
                $table->timestamps();

                $table->index(['requisicion_id', 'estado'], 'idx_imp_cot_req_estado');
            });
        }

        Schema::table('cotizaciones', function (Blueprint $table): void {
            if (! Schema::hasColumn('cotizaciones', 'origen_registro')) {
                $table->string('origen_registro', 24)->default('MANUAL')->after('estado');
            }
            if (! Schema::hasColumn('cotizaciones', 'archivo_original_nombre')) {
                $table->string('archivo_original_nombre', 255)->nullable()->after('origen_registro');
            }
            if (! Schema::hasColumn('cotizaciones', 'archivo_original_path')) {
                $table->string('archivo_original_path', 500)->nullable()->after('archivo_original_nombre');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones_cotizacion_proveedor');

        Schema::table('cotizaciones', function (Blueprint $table): void {
            $columnas = collect([
                'origen_registro',
                'archivo_original_nombre',
                'archivo_original_path',
            ])->filter(fn(string $columna): bool => Schema::hasColumn('cotizaciones', $columna))->all();

            if ($columnas !== []) {
                $table->dropColumn($columnas);
            }
        });
    }
};
