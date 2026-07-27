<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('requisicion_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('requisicion_id')
                ->constrained('requisiciones')
                ->cascadeOnDelete();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();

            $table->decimal('cantidad_solicitada', 14, 3);
            $table->decimal('cantidad_atendida', 14, 3)->default(0);
            $table->string('observacion', 300)->nullable();
            $table->timestamps();

            $table->unique(
                ['requisicion_id', 'producto_id'],
                'uq_requisicion_producto'
            );

            $table->index('producto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisicion_detalles');
    }
};
