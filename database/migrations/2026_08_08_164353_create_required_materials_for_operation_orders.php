<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Esta migración crea tablas nuevas. Si un intento anterior falló en MySQL
         * durante la creación de una FK, el DDL pudo haber dejado tablas parciales
         * aunque Laravel no haya registrado la migración como ejecutada.
         *
         * Se eliminan únicamente estas dos tablas nuevas (historial primero por las
         * posibles dependencias) para poder reconstruirlas de forma consistente.
         */
        Schema::dropIfExists('historial_materiales_requeridos_orden');
        Schema::dropIfExists('materiales_requeridos_orden');

        Schema::create('materiales_requeridos_orden', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orden_operacion_id');
            $table->foreignId('producto_id');
            $table->decimal('cantidad_requerida', 14, 3);
            $table->string('observacion', 500)->nullable();
            $table->foreignId('creado_por')->nullable();
            $table->foreignId('actualizado_por')->nullable();
            $table->timestamps();

            // Nombres explícitos y cortos: MySQL limita identificadores a 64 caracteres.
            $table->foreign('orden_operacion_id', 'fk_mat_req_orden')
                ->references('id')->on('ordenes_operacion')
                ->restrictOnDelete();
            $table->foreign('producto_id', 'fk_mat_req_producto')
                ->references('id')->on('productos')
                ->restrictOnDelete();
            $table->foreign('creado_por', 'fk_mat_req_creado_por')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('actualizado_por', 'fk_mat_req_actualizado_por')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->unique(
                ['orden_operacion_id', 'producto_id'],
                'uq_material_requerido_orden_producto'
            );
            $table->index(
                ['orden_operacion_id', 'updated_at'],
                'idx_material_requerido_orden_fecha'
            );
        });

        Schema::create('historial_materiales_requeridos_orden', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_requerido_orden_id');
            $table->foreignId('orden_operacion_id');
            $table->foreignId('producto_id');
            $table->string('tipo_movimiento', 30);
            $table->decimal('cantidad_anterior', 14, 3)->default(0);
            $table->decimal('cantidad_cambio', 14, 3);
            $table->decimal('cantidad_nueva', 14, 3);
            $table->string('motivo', 500)->nullable();
            $table->foreignId('registrado_por')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Evitar nombres automáticos excesivamente largos en MySQL.
            $table->foreign('material_requerido_orden_id', 'fk_hist_mat_req_material')
                ->references('id')->on('materiales_requeridos_orden')
                ->restrictOnDelete();
            $table->foreign('orden_operacion_id', 'fk_hist_mat_req_orden')
                ->references('id')->on('ordenes_operacion')
                ->restrictOnDelete();
            $table->foreign('producto_id', 'fk_hist_mat_req_producto')
                ->references('id')->on('productos')
                ->restrictOnDelete();
            $table->foreign('registrado_por', 'fk_hist_mat_req_usuario')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index(
                ['material_requerido_orden_id', 'created_at'],
                'idx_hist_material_requerido_fecha'
            );
            $table->index(
                ['orden_operacion_id', 'producto_id'],
                'idx_hist_material_orden_producto'
            );
        });

        // Órdenes OM/OS/OP ya existentes: la cotización origen constituye
        // la lista inicial aprobada. Se migran solo líneas con producto real.
        $existentes = DB::table('cotizaciones_cliente as c')
            ->join('ordenes_operacion as o', 'o.id', '=', 'c.orden_operacion_id')
            ->join('tipos_orden as t', 't.id', '=', 'o.tipo_orden_id')
            ->join('cotizacion_cliente_detalles as d', 'd.cotizacion_cliente_id', '=', 'c.id')
            ->whereNotNull('c.orden_operacion_id')
            ->whereNotNull('d.producto_id')
            ->whereIn('t.codigo', ['OM', 'OS', 'OP'])
            ->groupBy('o.id', 'd.producto_id', 'o.creado_por')
            ->selectRaw('o.id as orden_id, d.producto_id, o.creado_por, SUM(d.cantidad) as cantidad')
            ->get();

        foreach ($existentes as $fila) {
            $cantidad = round((float) $fila->cantidad, 3);
            if ($cantidad <= 0) {
                continue;
            }

            $ahora = now();
            $materialId = DB::table('materiales_requeridos_orden')->insertGetId([
                'orden_operacion_id' => $fila->orden_id,
                'producto_id' => $fila->producto_id,
                'cantidad_requerida' => $cantidad,
                'observacion' => 'Requerimiento inicial migrado desde la cotización origen.',
                'creado_por' => $fila->creado_por,
                'actualizado_por' => $fila->creado_por,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            DB::table('historial_materiales_requeridos_orden')->insert([
                'material_requerido_orden_id' => $materialId,
                'orden_operacion_id' => $fila->orden_id,
                'producto_id' => $fila->producto_id,
                'tipo_movimiento' => 'INICIAL',
                'cantidad_anterior' => 0,
                'cantidad_cambio' => $cantidad,
                'cantidad_nueva' => $cantidad,
                'motivo' => 'Lista inicial aprobada en la cotización origen.',
                'registrado_por' => $fila->creado_por,
                'created_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_materiales_requeridos_orden');
        Schema::dropIfExists('materiales_requeridos_orden');
    }
};
