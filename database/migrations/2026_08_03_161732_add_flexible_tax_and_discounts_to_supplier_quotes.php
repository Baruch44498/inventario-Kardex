<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->agregarColumnasCotizacion();
        $this->agregarColumnasDetalle();
        $this->actualizarDatosExistentes();
    }

    public function down(): void
    {
        $columnasDetalle = array_values(array_filter([
            Schema::hasColumn('cotizacion_detalles', 'descuento_modo') ? 'descuento_modo' : null,
            Schema::hasColumn('cotizacion_detalles', 'descuento_tipo') ? 'descuento_tipo' : null,
            Schema::hasColumn('cotizacion_detalles', 'descuento_valor') ? 'descuento_valor' : null,
            Schema::hasColumn('cotizacion_detalles', 'igv_modo') ? 'igv_modo' : null,
            Schema::hasColumn('cotizacion_detalles', 'igv_porcentaje') ? 'igv_porcentaje' : null,
            Schema::hasColumn('cotizacion_detalles', 'impuesto') ? 'impuesto' : null,
            Schema::hasColumn('cotizacion_detalles', 'total') ? 'total' : null,
        ]));

        if ($columnasDetalle !== []) {
            Schema::table('cotizacion_detalles', function (Blueprint $table) use ($columnasDetalle): void {
                $table->dropColumn($columnasDetalle);
            });
        }

        $columnasCotizacion = array_values(array_filter([
            Schema::hasColumn('cotizaciones', 'descuento_global_modo') ? 'descuento_global_modo' : null,
            Schema::hasColumn('cotizaciones', 'descuento_global_tipo') ? 'descuento_global_tipo' : null,
            Schema::hasColumn('cotizaciones', 'descuento_global_valor') ? 'descuento_global_valor' : null,
            Schema::hasColumn('cotizaciones', 'descuento_global_monto') ? 'descuento_global_monto' : null,
        ]));

        if ($columnasCotizacion !== []) {
            Schema::table('cotizaciones', function (Blueprint $table) use ($columnasCotizacion): void {
                $table->dropColumn($columnasCotizacion);
            });
        }
    }

    private function agregarColumnasCotizacion(): void
    {
        if (! Schema::hasColumn('cotizaciones', 'descuento_global_modo')) {
            Schema::table('cotizaciones', function (Blueprint $table): void {
                $table->string('descuento_global_modo', 24)
                    ->default('SIN_DESCUENTO')
                    ->after('tipo_cambio');
            });
        }

        if (! Schema::hasColumn('cotizaciones', 'descuento_global_tipo')) {
            Schema::table('cotizaciones', function (Blueprint $table): void {
                $table->string('descuento_global_tipo', 20)
                    ->nullable()
                    ->after('descuento_global_modo');
            });
        }

        if (! Schema::hasColumn('cotizaciones', 'descuento_global_valor')) {
            Schema::table('cotizaciones', function (Blueprint $table): void {
                $table->decimal('descuento_global_valor', 14, 4)
                    ->nullable()
                    ->after('descuento_global_tipo');
            });
        }

        if (! Schema::hasColumn('cotizaciones', 'descuento_global_monto')) {
            Schema::table('cotizaciones', function (Blueprint $table): void {
                $table->decimal('descuento_global_monto', 14, 4)
                    ->default(0)
                    ->after('subtotal');
            });
        }
    }

    private function agregarColumnasDetalle(): void
    {
        $columnas = [
            'descuento_modo' => fn(Blueprint $table) => $table
                ->string('descuento_modo', 24)->default('SIN_DESCUENTO')
                ->after('descuento_porcentaje'),
            'descuento_tipo' => fn(Blueprint $table) => $table
                ->string('descuento_tipo', 20)->nullable()->after('descuento_modo'),
            'descuento_valor' => fn(Blueprint $table) => $table
                ->decimal('descuento_valor', 14, 4)->nullable()->after('descuento_tipo'),
            'igv_modo' => fn(Blueprint $table) => $table
                ->string('igv_modo', 20)->default('AGREGAR')->after('descuento_valor'),
            'igv_porcentaje' => fn(Blueprint $table) => $table
                ->decimal('igv_porcentaje', 7, 4)->default(18)->after('igv_modo'),
            'impuesto' => fn(Blueprint $table) => $table
                ->decimal('impuesto', 14, 4)->default(0)->after('subtotal'),
            'total' => fn(Blueprint $table) => $table
                ->decimal('total', 14, 4)->default(0)->after('impuesto'),
        ];

        foreach ($columnas as $nombre => $definir) {
            if (! Schema::hasColumn('cotizacion_detalles', $nombre)) {
                Schema::table('cotizacion_detalles', function (Blueprint $table) use ($definir): void {
                    $definir($table);
                });
            }
        }
    }

    private function actualizarDatosExistentes(): void
    {
        DB::table('cotizacion_detalles as cd')
            ->join('cotizaciones as c', 'c.id', '=', 'cd.cotizacion_id')
            ->select([
                'cd.id',
                'cd.descuento_porcentaje',
                'cd.subtotal',
                'c.impuesto as impuesto_cotizacion',
            ])
            ->orderBy('cd.id')
            ->get()
            ->each(function ($detalle): void {
                $tieneDescuento = (float) $detalle->descuento_porcentaje > 0;
                $aplicaIgv = (float) $detalle->impuesto_cotizacion > 0;
                $impuesto = $aplicaIgv
                    ? round((float) $detalle->subtotal * 0.18, 4)
                    : 0.0;

                DB::table('cotizacion_detalles')
                    ->where('id', $detalle->id)
                    ->update([
                        'descuento_modo' => $tieneDescuento
                            ? 'APLICAR'
                            : 'SIN_DESCUENTO',
                        'descuento_tipo' => $tieneDescuento
                            ? 'PORCENTAJE'
                            : null,
                        'descuento_valor' => $tieneDescuento
                            ? $detalle->descuento_porcentaje
                            : null,
                        'igv_modo' => $aplicaIgv ? 'AGREGAR' : 'NO_APLICA',
                        'igv_porcentaje' => $aplicaIgv ? 18 : 0,
                        'impuesto' => $impuesto,
                        'total' => round((float) $detalle->subtotal + $impuesto, 4),
                    ]);
            });
    }
};
