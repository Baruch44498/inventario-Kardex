<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('historial_requerimientos_compra')) {
            Schema::create('historial_requerimientos_compra', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('requisicion_id');
                $table->string('estado_anterior', 30)->nullable();
                $table->string('estado_nuevo', 30);
                $table->text('observacion')->nullable();
                $table->unsignedBigInteger('registrado_por')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('requisicion_id', 'fk_hist_req_compra_req')
                    ->references('id')->on('requisiciones')->restrictOnDelete();
                $table->foreign('registrado_por', 'fk_hist_req_compra_user')
                    ->references('id')->on('users')->nullOnDelete();
                $table->index(['requisicion_id', 'created_at'], 'idx_hist_req_compra_fecha');
            });
        }

        // Los requerimientos existentes reciben un punto de partida en el historial.
        // No modifica su estado: solo documenta la situación encontrada al instalar 17.1.1.
        DB::table('requisiciones')
            ->orderBy('id')
            ->chunkById(200, function ($requerimientos): void {
                foreach ($requerimientos as $requerimiento) {
                    $yaExiste = DB::table('historial_requerimientos_compra')
                        ->where('requisicion_id', $requerimiento->id)
                        ->exists();

                    if ($yaExiste) {
                        continue;
                    }

                    $actor = $requerimiento->atendido_por
                        ?? $requerimiento->recibido_por
                        ?? $requerimiento->enviado_por
                        ?? $requerimiento->solicitado_por
                        ?? null;

                    DB::table('historial_requerimientos_compra')->insert([
                        'requisicion_id' => $requerimiento->id,
                        'estado_anterior' => null,
                        'estado_nuevo' => $requerimiento->estado,
                        'observacion' => 'Estado inicial registrado al habilitar el historial de atención.',
                        'registrado_por' => $actor,
                        'created_at' => property_exists($requerimiento, 'updated_at') && $requerimiento->updated_at
                            ? $requerimiento->updated_at
                            : now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_requerimientos_compra');
    }
};
