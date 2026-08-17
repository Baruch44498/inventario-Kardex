<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avances_orden_operacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orden_operacion_id')
                ->constrained('ordenes_operacion')
                ->restrictOnDelete();
            $table->decimal('porcentaje', 5, 2);
            $table->string('detalle', 500);
            $table->foreignId('registrado_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('registrado_en')->useCurrent();
            $table->timestamps();

            $table->index(
                ['orden_operacion_id', 'registrado_en'],
                'idx_avance_orden_fecha'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avances_orden_operacion');
    }
};
