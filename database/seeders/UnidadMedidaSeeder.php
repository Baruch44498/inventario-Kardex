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
                'codigo' => 'UND',
                'nombre' => 'Unidad',
                'estado' => true,
            ],
            [
                'codigo' => 'KG',
                'nombre' => 'Kilogramo',
                'estado' => true,
            ],
            [
                'codigo' => 'M',
                'nombre' => 'Metro',
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
    }
}