<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'codigo' => 'ADMINISTRADOR',
                'nombre' => 'Administrador',
                'descripcion' => 'Configuración general y gestión de usuarios.',
            ],
            [
                'codigo' => 'ALMACEN',
                'nombre' => 'Encargado de almacén',
                'descripcion' => 'Inventario, requisiciones, entradas y salidas.',
            ],
            [
                'codigo' => 'JEFE_COMPRAS',
                'nombre' => 'Jefe de compras',
                'descripcion' => 'Proveedores, solicitudes de compra y cotizaciones.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['codigo' => $role['codigo']],
                $role + ['estado' => true]
            );
        }
    }
}
