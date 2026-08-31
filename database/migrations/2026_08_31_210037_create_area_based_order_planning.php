<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cotizacion_cliente_id')
                ->constrained('cotizaciones_cliente')
                ->cascadeOnDelete();
            $table->foreignId('componente_origen_id')
                ->nullable()
                ->constrained('cotizacion_componentes')
                ->nullOnDelete();
            $table->foreignId('area_padre_id')
                ->nullable()
                ->constrained('cotizacion_areas')
                ->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('nombre_normalizado', 150);
            $table->unsignedInteger('orden_secuencia')->default(1);
            $table->string('origen', 20)->default('MANUAL');
            $table->string('estado', 20)->default('VIGENTE');
            $table->timestamps();

            $table->index(
                ['cotizacion_cliente_id', 'area_padre_id', 'orden_secuencia'],
                'idx_cot_area_jerarquia'
            );
            $table->index(
                ['cotizacion_cliente_id', 'nombre_normalizado'],
                'idx_cot_area_nombre'
            );
        });

        Schema::table('cotizacion_presupuestos', function (Blueprint $table): void {
            $table->foreignId('cotizacion_area_id')
                ->nullable()
                ->after('componente_id')
                ->constrained('cotizacion_areas')
                ->nullOnDelete();
            $table->string('ejecucion_servicio', 25)
                ->nullable()
                ->after('tipo_costo');
            $table->index(
                ['cotizacion_area_id', 'tipo_costo', 'estado'],
                'idx_presupuesto_area_tipo_estado'
            );
        });

        Schema::table('ordenes_operacion', function (Blueprint $table): void {
            $table->foreignId('orden_padre_id')
                ->nullable()
                ->after('cotizacion_cliente_id')
                ->constrained('ordenes_operacion')
                ->nullOnDelete();
            $table->foreignId('presupuesto_servicio_origen_id')
                ->nullable()
                ->after('orden_padre_id')
                ->constrained('cotizacion_presupuestos')
                ->nullOnDelete();
            $table->index(
                ['orden_padre_id', 'estado'],
                'idx_orden_padre_estado'
            );
        });

        Schema::create('orden_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orden_operacion_id')
                ->constrained('ordenes_operacion')
                ->cascadeOnDelete();
            $table->foreignId('cotizacion_area_id')
                ->nullable()
                ->constrained('cotizacion_areas')
                ->nullOnDelete();
            $table->foreignId('area_padre_id')
                ->nullable()
                ->constrained('orden_areas')
                ->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('nombre_normalizado', 150);
            $table->unsignedInteger('orden_secuencia')->default(1);
            $table->string('origen', 20)->default('COTIZACION');
            $table->string('estado', 20)->default('ACTIVA');
            $table->timestamps();

            $table->unique(
                ['orden_operacion_id', 'cotizacion_area_id'],
                'uq_orden_area_cotizacion'
            );
            $table->index(
                ['orden_operacion_id', 'nombre_normalizado'],
                'idx_orden_area_nombre'
            );
        });

        Schema::create('materiales_planificados_orden_area', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orden_operacion_id')
                ->constrained('ordenes_operacion')
                ->cascadeOnDelete();
            $table->foreignId('orden_area_id')
                ->constrained('orden_areas')
                ->cascadeOnDelete();
            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();
            $table->string('codigo_producto', 80);
            $table->string('descripcion_producto', 300);
            $table->string('unidad', 20);
            $table->decimal('cantidad_estimada', 14, 3);
            $table->decimal('costo_unitario_estimado_soles', 14, 4)->default(0);
            $table->decimal('costo_total_estimado_soles', 20, 4)->default(0);
            $table->timestamp('congelado_en');
            $table->timestamps();

            $table->unique(
                ['orden_area_id', 'producto_id'],
                'uq_plan_area_producto'
            );
            $table->index(
                ['orden_operacion_id', 'producto_id'],
                'idx_plan_orden_producto'
            );
        });

        Schema::table('notas_salida', function (Blueprint $table): void {
            $table->foreignId('orden_area_id')
                ->nullable()
                ->after('orden_operacion_id')
                ->constrained('orden_areas')
                ->nullOnDelete();
        });

        Schema::table('notas_ingreso', function (Blueprint $table): void {
            $table->foreignId('orden_area_id')
                ->nullable()
                ->after('orden_operacion_id')
                ->constrained('orden_areas')
                ->nullOnDelete();
        });

        $this->migrarGruposExistentes();
        $this->migrarOrdenesExistentes();

        DB::table('cotizacion_presupuestos')
            ->where('tipo_costo', 'SERVICIO_TERCERO')
            ->whereNull('ejecucion_servicio')
            ->update(['ejecucion_servicio' => 'EXTERNO']);
    }

    public function down(): void
    {
        Schema::table('notas_ingreso', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('orden_area_id');
        });
        Schema::table('notas_salida', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('orden_area_id');
        });

        Schema::dropIfExists('materiales_planificados_orden_area');
        Schema::dropIfExists('orden_areas');

        Schema::table('ordenes_operacion', function (Blueprint $table): void {
            $table->dropForeign(['presupuesto_servicio_origen_id']);
            $table->dropForeign(['orden_padre_id']);
            $table->dropIndex('idx_orden_padre_estado');
            $table->dropColumn(['presupuesto_servicio_origen_id', 'orden_padre_id']);
        });

        Schema::table('cotizacion_presupuestos', function (Blueprint $table): void {
            $table->dropForeign(['cotizacion_area_id']);
            $table->dropIndex('idx_presupuesto_area_tipo_estado');
            $table->dropColumn(['cotizacion_area_id', 'ejecucion_servicio']);
        });

        Schema::dropIfExists('cotizacion_areas');
    }

    private function migrarGruposExistentes(): void
    {
        $areas = [];
        $secuencias = [];

        DB::table('cotizacion_presupuestos')
            ->where('tipo_costo', 'MATERIAL')
            ->where('estado', 'VIGENTE')
            ->orderBy('id')
            ->get(['id', 'cotizacion_cliente_id', 'componente_id', 'grupo_costo'])
            ->each(function (object $partida) use (&$areas, &$secuencias): void {
                $nombre = $this->limpiar((string) $partida->grupo_costo) ?: 'GENERAL';
                $normalizado = $this->normalizar($nombre);
                $clave = $partida->cotizacion_cliente_id . ':' . $normalizado;

                if (! isset($areas[$clave])) {
                    $cotizacionId = (int) $partida->cotizacion_cliente_id;
                    $secuencias[$cotizacionId] = ($secuencias[$cotizacionId] ?? 0) + 1;
                    $areas[$clave] = DB::table('cotizacion_areas')->insertGetId([
                        'cotizacion_cliente_id' => $cotizacionId,
                        'componente_origen_id' => $partida->componente_id,
                        'area_padre_id' => null,
                        'nombre' => $nombre,
                        'nombre_normalizado' => $normalizado,
                        'orden_secuencia' => $secuencias[$cotizacionId],
                        'origen' => 'LEGADO',
                        'estado' => 'VIGENTE',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('cotizacion_presupuestos')
                    ->where('id', $partida->id)
                    ->update(['cotizacion_area_id' => $areas[$clave]]);
            });
    }

    private function migrarOrdenesExistentes(): void
    {
        $ordenAreas = [];

        DB::table('cotizacion_areas')
            ->orderBy('id')
            ->get()
            ->each(function (object $area) use (&$ordenAreas): void {
                $ordenId = $area->componente_origen_id
                    ? DB::table('cotizacion_componentes')
                    ->where('id', $area->componente_origen_id)
                    ->value('orden_operacion_id')
                    : null;

                $ordenId ??= DB::table('ordenes_operacion')
                    ->where('cotizacion_cliente_id', $area->cotizacion_cliente_id)
                    ->orderBy('id')
                    ->value('id');

                if (! $ordenId) {
                    return;
                }

                $ordenAreaId = DB::table('orden_areas')->insertGetId([
                    'orden_operacion_id' => $ordenId,
                    'cotizacion_area_id' => $area->id,
                    'area_padre_id' => null,
                    'nombre' => $area->nombre,
                    'nombre_normalizado' => $area->nombre_normalizado,
                    'orden_secuencia' => $area->orden_secuencia,
                    'origen' => 'COTIZACION',
                    'estado' => 'ACTIVA',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $ordenAreas[(int) $area->id] = $ordenAreaId;
            });

        foreach ($ordenAreas as $cotizacionAreaId => $ordenAreaId) {
            $ordenArea = DB::table('orden_areas')->where('id', $ordenAreaId)->first();
            $partidas = DB::table('cotizacion_presupuestos')
                ->where('cotizacion_area_id', $cotizacionAreaId)
                ->where('tipo_costo', 'MATERIAL')
                ->where('estado', 'VIGENTE')
                ->whereNotNull('producto_id')
                ->get()
                ->groupBy('producto_id');

            foreach ($partidas as $productoId => $lineas) {
                $producto = DB::table('productos as p')
                    ->leftJoin('unidades_medida as u', 'u.id', '=', 'p.unidad_medida_id')
                    ->where('p.id', $productoId)
                    ->first(['p.codigo', 'p.descripcion', 'u.codigo as unidad']);
                if (! $producto) {
                    continue;
                }

                $cantidad = round((float) $lineas->sum('cantidad'), 3);
                $total = round((float) $lineas->sum('costo_total_soles'), 4);
                DB::table('materiales_planificados_orden_area')->insert([
                    'orden_operacion_id' => $ordenArea->orden_operacion_id,
                    'orden_area_id' => $ordenAreaId,
                    'producto_id' => $productoId,
                    'codigo_producto' => $producto->codigo,
                    'descripcion_producto' => $producto->descripcion,
                    'unidad' => $producto->unidad ?: 'UNIDAD',
                    'cantidad_estimada' => $cantidad,
                    'costo_unitario_estimado_soles' => $cantidad > 0 ? round($total / $cantidad, 4) : 0,
                    'costo_total_estimado_soles' => $total,
                    'congelado_en' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (['notas_salida', 'notas_ingreso'] as $tabla) {
                DB::table($tabla)
                    ->where('orden_operacion_id', $ordenArea->orden_operacion_id)
                    ->whereNotNull('area_trabajo')
                    ->get(['id', 'area_trabajo'])
                    ->each(function (object $nota) use ($tabla, $ordenArea, $ordenAreaId): void {
                        if ($this->normalizar($nota->area_trabajo) === $ordenArea->nombre_normalizado) {
                            DB::table($tabla)
                                ->where('id', $nota->id)
                                ->update(['orden_area_id' => $ordenAreaId]);
                        }
                    });
            }
        }
    }

    private function limpiar(string $valor): string
    {
        return trim(preg_replace('/\s+/u', ' ', $valor) ?? '');
    }

    private function normalizar(string $valor): string
    {
        return mb_strtoupper($this->limpiar($valor));
    }
};
