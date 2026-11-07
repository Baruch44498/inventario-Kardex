<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_periodico_repisas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventario_periodico_id')->constrained('inventarios_periodicos')->restrictOnDelete();
            $table->foreignId('repisa_id')->constrained('repisas')->restrictOnDelete();
            $table->timestamp('vacia_confirmada_en')->nullable();
            $table->foreignId('vacia_confirmada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hallazgo_descripcion', 300)->nullable();
            $table->decimal('hallazgo_cantidad', 14, 3)->nullable();
            $table->timestamp('hallazgo_en')->nullable();
            $table->foreignId('hallazgo_por')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['inventario_periodico_id', 'repisa_id'], 'uq_periodico_repisa');
            $table->index(['repisa_id', 'inventario_periodico_id'], 'idx_periodico_repisa');
        });

        // Preserve every count created before grouped counting was introduced.
        DB::table('inventarios_periodicos')->select('id', 'repisa_id')->orderBy('id')
            ->chunkById(500, function ($periodicos): void {
                DB::table('inventario_periodico_repisas')->insert($periodicos->map(fn ($periodico) => [
                    'inventario_periodico_id' => $periodico->id,
                    'repisa_id' => $periodico->repisa_id,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_periodico_repisas');
    }
};
