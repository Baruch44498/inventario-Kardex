<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerta_stock_requisicion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alerta_stock_id')
                ->constrained('alertas_stock')
                ->cascadeOnDelete();
            $table->foreignId('requisicion_id')
                ->constrained('requisiciones')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['alerta_stock_id', 'requisicion_id'],
                'uq_alerta_requisicion'
            );
            $table->index(
                ['requisicion_id', 'alerta_stock_id'],
                'idx_requisicion_alerta'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerta_stock_requisicion');
    }
};
