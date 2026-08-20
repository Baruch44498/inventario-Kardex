<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion_componentes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cotizacion_cliente_id')
                ->constrained('cotizaciones_cliente')
                ->cascadeOnDelete();
            $table->foreignId('tipo_orden_id')
                ->constrained('tipos_orden')
                ->restrictOnDelete();
            $table->string('descripcion_componente', 500);
            $table->foreignId('cliente_direccion_id')
                ->nullable()
                ->constrained('cliente_direcciones')
                ->nullOnDelete();
            $table->foreignId('vehiculo_id')
                ->nullable()
                ->constrained('vehiculos')
                ->nullOnDelete();
            $table->decimal('tipo_cambio_comparacion', 14, 6)->nullable();
            $table->unsignedInteger('orden_secuencia')->default(1);
            $table->foreignId('orden_operacion_id')
                ->nullable()
                ->constrained('ordenes_operacion')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['cotizacion_cliente_id', 'orden_secuencia'],
                'uq_cotizacion_componente_secuencia'
            );
            $table->unique('orden_operacion_id', 'uq_componente_orden_operacion');
        });

        Schema::table('cotizacion_cliente_detalles', function (Blueprint $table): void {
            // MySQL no permite eliminar un índice que soporta una FK.
            // Hay que soltar la FK primero, luego el índice, luego reconstruir.
            $table->dropForeign(['cotizacion_cliente_id']);
            $table->dropUnique('uq_cotizacion_cliente_producto');

            $table->foreignId('componente_id')
                ->nullable()
                ->after('cotizacion_cliente_id')
                ->constrained('cotizacion_componentes')
                ->nullOnDelete();

            // El nuevo unique empieza en cotizacion_cliente_id → MySQL lo usa
            // automáticamente como índice de soporte para la FK re-declarada.
            $table->unique(
                ['cotizacion_cliente_id', 'componente_id', 'producto_id'],
                'uq_cotizacion_componente_producto'
            );

            // Re-declarar la FK de cotizacion_cliente_id usando el nuevo índice.
            $table->foreign('cotizacion_cliente_id')
                ->references('id')
                ->on('cotizaciones_cliente')
                ->restrictOnDelete();
        });

        Schema::table('cotizacion_presupuestos', function (Blueprint $table): void {
            $table->foreignId('componente_id')
                ->nullable()
                ->after('cotizacion_cliente_id')
                ->constrained('cotizacion_componentes')
                ->nullOnDelete();
            $table->index(
                ['componente_id', 'estado'],
                'idx_presupuesto_componente_estado'
            );
        });

        Schema::table('ordenes_operacion', function (Blueprint $table): void {
            $table->foreignId('cotizacion_cliente_id')
                ->nullable()
                ->after('tipo_orden_id')
                ->constrained('cotizaciones_cliente')
                ->nullOnDelete();
        });

        $ahora = now();
        DB::table('cotizaciones_cliente')
            ->whereNull('proforma_id')
            ->whereNotNull('tipo_orden_id')
            ->orderBy('id')
            ->get()
            ->each(function ($cotizacion) use ($ahora): void {
                $componenteId = DB::table('cotizacion_componentes')->insertGetId([
                    'cotizacion_cliente_id' => $cotizacion->id,
                    'tipo_orden_id' => $cotizacion->tipo_orden_id,
                    'descripcion_componente' => $cotizacion->descripcion_trabajo
                        ?: 'Trabajo de la cotización ' . $cotizacion->codigo,
                    'cliente_direccion_id' => $cotizacion->cliente_direccion_id,
                    'vehiculo_id' => $cotizacion->vehiculo_id,
                    'tipo_cambio_comparacion' => $cotizacion->tipo_cambio,
                    'orden_secuencia' => 1,
                    'orden_operacion_id' => $cotizacion->orden_operacion_id,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

                DB::table('cotizacion_cliente_detalles')
                    ->where('cotizacion_cliente_id', $cotizacion->id)
                    ->update(['componente_id' => $componenteId]);
                DB::table('cotizacion_presupuestos')
                    ->where('cotizacion_cliente_id', $cotizacion->id)
                    ->update(['componente_id' => $componenteId]);

                if ($cotizacion->orden_operacion_id) {
                    DB::table('ordenes_operacion')
                        ->where('id', $cotizacion->orden_operacion_id)
                        ->update(['cotizacion_cliente_id' => $cotizacion->id]);
                }
            });
    }

    public function down(): void
    {
        $tieneProductosDuplicados = DB::table('cotizacion_cliente_detalles')
            ->select('cotizacion_cliente_id', 'producto_id')
            ->whereNotNull('producto_id')
            ->groupBy('cotizacion_cliente_id', 'producto_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($tieneProductosDuplicados) {
            throw new RuntimeException(
                'No se puede revertir la cotización multi-componente: '
                    . 'existen productos repetidos entre componentes de una misma cotización.'
            );
        }

        Schema::table('ordenes_operacion', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cotizacion_cliente_id');
        });

        Schema::table('cotizacion_presupuestos', function (Blueprint $table): void {
            $table->dropIndex('idx_presupuesto_componente_estado');
            $table->dropConstrainedForeignId('componente_id');
        });

        Schema::table('cotizacion_cliente_detalles', function (Blueprint $table): void {
            // La FK usa el índice compuesto como soporte en MySQL. Debe
            // retirarse antes de eliminar dicho índice para evitar el error 1553.
            $table->dropForeign(['cotizacion_cliente_id']);
            $table->dropUnique('uq_cotizacion_componente_producto');
            $table->dropConstrainedForeignId('componente_id');
            $table->unique(
                ['cotizacion_cliente_id', 'producto_id'],
                'uq_cotizacion_cliente_producto'
            );
            $table->foreign('cotizacion_cliente_id')
                ->references('id')
                ->on('cotizaciones_cliente')
                ->cascadeOnDelete();
        });

        Schema::dropIfExists('cotizacion_componentes');
    }
};
