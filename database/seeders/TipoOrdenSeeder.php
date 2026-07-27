<?php

namespace Database\Seeders;

use App\Models\TipoOrden;
use Illuminate\Database\Seeder;

class TipoOrdenSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['codigo' => 'OP', 'nombre' => 'Orden de producción'],
            ['codigo' => 'OS', 'nombre' => 'Orden de servicio'],
            ['codigo' => 'OM', 'nombre' => 'Orden de mantenimiento'],
            ['codigo' => 'OV', 'nombre' => 'Orden de venta'],
        ];

        foreach ($tipos as $tipo) {
            TipoOrden::updateOrCreate(
                ['codigo' => $tipo['codigo']],
                $tipo + ['descripcion' => null, 'estado' => true]
            );
        }
    }
}
