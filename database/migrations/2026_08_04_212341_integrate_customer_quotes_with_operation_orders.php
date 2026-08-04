<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones_cliente', function (Blueprint $table): void {
            $table->foreignId('tipo_orden_id')
                ->nullable()
                ->after('cliente_id')
                ->constrained('tipos_orden')
                ->restrictOnDelete();
            $table->foreignId('cliente_direccion_id')
                ->nullable()
                ->after('tipo_orden_id')
                ->constrained('cliente_direcciones')
                ->restrictOnDelete();
            $table->foreignId('vehiculo_id')
                ->nullable()
                ->after('cliente_direccion_id')
                ->constrained('vehiculos')
                ->restrictOnDelete();
            $table->string('descripcion_trabajo', 500)
                ->nullable()
                ->after('vehiculo_id');

            $table->index(
                ['tipo_orden_id', 'estado'],
                'idx_cotizacion_tipo_estado'
            );
        });

        // Conserva la trazabilidad de las cotizaciones que ya originaron una
        // orden antes de este parche.
        DB::table('cotizaciones_cliente')
            ->whereNotNull('orden_operacion_id')
            ->orderBy('id')
            ->chunkById(100, function ($cotizaciones): void {
                foreach ($cotizaciones as $cotizacion) {
                    $orden = DB::table('ordenes_operacion')
                        ->where('id', $cotizacion->orden_operacion_id)
                        ->first([
                            'tipo_orden_id',
                            'cliente_direccion_id',
                            'vehiculo_id',
                            'descripcion',
                        ]);

                    if (! $orden) {
                        continue;
                    }

                    DB::table('cotizaciones_cliente')
                        ->where('id', $cotizacion->id)
                        ->update([
                            'tipo_orden_id' => $orden->tipo_orden_id,
                            'cliente_direccion_id' => $orden->cliente_direccion_id,
                            'vehiculo_id' => $orden->vehiculo_id,
                            'descripcion_trabajo' => $orden->descripcion,
                        ]);
                }
            });

        // Las cotizaciones que nacen de una proforma de Almacén pertenecen al
        // flujo de venta directa y, por definición, originan una OV.
        $tipoVentaId = DB::table('tipos_orden')
            ->where('codigo', 'OV')
            ->value('id');

        if ($tipoVentaId) {
            DB::table('cotizaciones_cliente')
                ->whereNotNull('proforma_id')
                ->whereNull('tipo_orden_id')
                ->update(['tipo_orden_id' => $tipoVentaId]);
        }
    }

    public function down(): void
    {
        Schema::table('cotizaciones_cliente', function (Blueprint $table): void {
            $table->dropIndex('idx_cotizacion_tipo_estado');
            $table->dropConstrainedForeignId('vehiculo_id');
            $table->dropConstrainedForeignId('cliente_direccion_id');
            $table->dropConstrainedForeignId('tipo_orden_id');
            $table->dropColumn('descripcion_trabajo');
        });
    }
};
