<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();
        $unidades = [
            'BAL' => 'Balde',
            'GLN' => 'Galón',
            'KIT' => 'Kit',
            'MTS' => 'Metros',
            'UND' => 'Unidad',
            'LT' => 'Litro',
        ];

        $metroAnterior = DB::table('unidades_medida')->where('codigo', 'M')->first();
        $metros = DB::table('unidades_medida')->where('codigo', 'MTS')->first();

        if ($metroAnterior && ! $metros) {
            DB::table('unidades_medida')
                ->where('id', $metroAnterior->id)
                ->update([
                    'codigo' => 'MTS',
                    'nombre' => 'Metros',
                    'estado' => true,
                    'updated_at' => $ahora,
                ]);
        } elseif ($metroAnterior && $metros) {
            DB::table('productos')
                ->where('unidad_medida_id', $metroAnterior->id)
                ->update(['unidad_medida_id' => $metros->id]);

            DB::table('unidades_medida')
                ->where('id', $metroAnterior->id)
                ->update(['estado' => false, 'updated_at' => $ahora]);
        }

        foreach ($unidades as $codigo => $nombre) {
            $existente = DB::table('unidades_medida')->where('codigo', $codigo)->exists();

            if ($existente) {
                DB::table('unidades_medida')->where('codigo', $codigo)->update([
                    'nombre' => $nombre,
                    'estado' => true,
                    'updated_at' => $ahora,
                ]);
            } else {
                DB::table('unidades_medida')->insert([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'estado' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }

        DB::table('unidades_medida')
            ->whereNotIn('codigo', array_keys($unidades))
            ->update(['estado' => false, 'updated_at' => $ahora]);

        Schema::table('nota_ingreso_detalles', function (Blueprint $table): void {
            $table->string('presentacion_nombre', 80)
                ->nullable()
                ->after('cantidad');
            $table->decimal('cantidad_presentacion', 14, 3)
                ->nullable()
                ->after('presentacion_nombre');
            $table->decimal('factor_conversion', 14, 3)
                ->default(1)
                ->after('cantidad_presentacion');
        });
    }

    public function down(): void
    {
        Schema::table('nota_ingreso_detalles', function (Blueprint $table): void {
            $table->dropColumn([
                'presentacion_nombre',
                'cantidad_presentacion',
                'factor_conversion',
            ]);
        });
    }
};
