<?php

namespace Database\Seeders;

use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

class UnidadMedidaSeeder extends Seeder
{
    public function run(): void
    {
        $unidades = [
            [
                'codigo' => 'BAL',
                'nombre' => 'Balde',
                'estado' => true,
            ],
            [
                'codigo' => 'GLN',
                'nombre' => 'Galón',
                'estado' => true,
            ],
            [
                'codigo' => 'KIT',
                'nombre' => 'Kit',
                'estado' => true,
            ],
            [
                'codigo' => 'MTS',
                'nombre' => 'Metros',
                'estado' => true,
            ],
            [
                'codigo' => 'UND',
                'nombre' => 'Unidad',
                'estado' => true,
            ],
            [
                'codigo' => 'LT',
                'nombre' => 'Litro',
                'estado' => true,
            ],
        ];

        foreach ($unidades as $unidad) {
            UnidadMedida::updateOrCreate(
                ['codigo' => $unidad['codigo']],
                $unidad
            );
        }

        UnidadMedida::query()
            ->whereNotIn('codigo', collect($unidades)->pluck('codigo'))
            ->update(['estado' => false]);
    }
}
