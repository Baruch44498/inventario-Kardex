<?php

namespace Database\Seeders;

use App\Models\TipoCliente;
use Illuminate\Database\Seeder;

class TipoClienteSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'codigo' => 'FINAL',
                'nombre' => 'Cliente final',
                'descripcion' => 'Compra para consumo o uso directo.',
            ],
            [
                'codigo' => 'MAYORISTA',
                'nombre' => 'Mayorista',
                'descripcion' => 'Compra por volumen o para redistribución.',
            ],
            [
                'codigo' => 'FABRICANTE',
                'nombre' => 'Fabricante',
                'descripcion' => 'Cliente industrial o de fabricación.',
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoCliente::query()->updateOrCreate(
                ['codigo' => $tipo['codigo']],
                $tipo + [
                    'porcentaje_ganancia' => 0,
                    'estado' => true,
                ]
            );
        }
    }
}
