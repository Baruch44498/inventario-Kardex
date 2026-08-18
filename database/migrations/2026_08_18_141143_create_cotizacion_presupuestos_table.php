<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion_presupuestos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cotizacion_cliente_id')
                ->constrained('cotizaciones_cliente')
                ->restrictOnDelete();
            $table->foreignId('producto_id')
                ->nullable()
                ->constrained('productos')
                ->nullOnDelete();
            $table->string('tipo_costo', 30);
            $table->string('descripcion', 300);
            $table->decimal('cantidad', 14, 3);
            $table->string('unidad', 20);
            $table->string('moneda', 3);
            $table->decimal('tipo_cambio', 14, 6);
            $table->decimal('costo_unitario', 14, 4);
            $table->decimal('carga_social_porcentaje', 7, 4)->default(0);
            $table->decimal('carga_social_original', 20, 4)->default(0);
            $table->string('igv_modo', 20)->default('NO_APLICA');
            $table->decimal('igv_porcentaje', 7, 4)->default(18);
            $table->decimal('costo_neto_original', 20, 4);
            $table->decimal('igv_original', 20, 4)->default(0);
            $table->decimal('costo_total_original', 20, 4);
            $table->decimal('costo_neto_soles', 20, 4);
            $table->decimal('igv_soles', 20, 4)->default(0);
            $table->decimal('costo_total_soles', 20, 4);
            $table->decimal('costo_neto_dolares', 20, 4);
            $table->decimal('igv_dolares', 20, 4)->default(0);
            $table->decimal('costo_total_dolares', 20, 4);
            $table->string('observacion', 500)->nullable();
            $table->string('estado', 20)->default('VIGENTE');
            $table->foreignId('registrado_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('actualizado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('registrado_en')->useCurrent();
            $table->foreignId('anulado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index(
                ['cotizacion_cliente_id', 'estado'],
                'idx_presupuesto_cotizacion_estado'
            );
            $table->index(['tipo_costo', 'estado'], 'idx_presupuesto_tipo_estado');
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_presupuestos');
    }
};
