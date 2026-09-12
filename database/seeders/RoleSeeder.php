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
                'descripcion' => 'Acceso total a ventas, compras, almacén, contabilidad y usuarios.',
            ],
            [
                'codigo' => 'COMERCIAL_LOGISTICA',
                'nombre' => 'Ventas y compras',
                'descripcion' => 'Clientes, cotizaciones, proveedores y abastecimiento.',
            ],
            [
                'codigo' => 'ALMACEN',
                'nombre' => 'Almacén',
                'descripcion' => 'Inventario, ingresos, salidas, alertas y Kardex.',
            ],
            [
                'codigo' => 'JEFE_PLANTA',
                'nombre' => 'Jefe de planta',
                'descripcion' => 'Rol heredado no asignable en la versión reducida.',
            ],
            [
                'codigo' => 'CONTABILIDAD',
                'nombre' => 'Contabilidad',
                'descripcion' => 'Facturas, cuentas por pagar y conciliación de compras.',
            ],
        ];

        foreach ($roles as $rol) {
            Role::query()->updateOrCreate(
                ['codigo' => $rol['codigo']],
                $rol + ['estado' => true]
            );
        }

        Role::query()
            ->where('codigo', 'JEFE_COMPRAS')
            ->update(['estado' => false]);
    }
}
