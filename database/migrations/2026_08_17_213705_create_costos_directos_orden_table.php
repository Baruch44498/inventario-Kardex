<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_directos_orden', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orden_operacion_id')
                ->constrained('ordenes_operacion')
                ->restrictOnDelete();
            $table->string('tipo', 30);
            $table->date('fecha_costo');
            $table->string('descripcion', 300);
            $table->foreignId('proveedor_id')
                ->nullable()
                ->constrained('proveedores')
                ->nullOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->string('unidad', 20);
            $table->decimal('costo_unitario_soles', 14, 4);
            $table->decimal('total_soles', 16, 4);
            $table->string('documento_referencia', 100)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->string('estado', 20)->default('VIGENTE');
            $table->foreignId('registrado_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('registrado_en')->useCurrent();
            $table->foreignId('anulado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index(['orden_operacion_id', 'estado'], 'idx_costo_directo_orden_estado');
            $table->index(['tipo', 'fecha_costo'], 'idx_costo_directo_tipo_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_directos_orden');
    }
};
