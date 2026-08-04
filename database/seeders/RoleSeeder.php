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
                'descripcion' => 'Acceso total, supervisión, Kardex, auditoría y usuarios.',
            ],
            [
                'codigo' => 'COMERCIAL_LOGISTICA',
                'nombre' => 'Comercial y logística',
                'descripcion' => 'Clientes, proveedores, compras, cotizaciones y órdenes OM, OV, OS y OP.',
            ],
            [
                'codigo' => 'ALMACEN',
                'nombre' => 'Almacén',
                'descripcion' => 'Inventario, ingresos, salidas, alertas y proformas de venta directa.',
            ],
            [
                'codigo' => 'JEFE_PLANTA',
                'nombre' => 'Jefe de planta',
                'descripcion' => 'Ejecución, avance y cierre operativo de órdenes.',
            ],
            [
                'codigo' => 'CONTABILIDAD',
                'nombre' => 'Contabilidad',
                'descripcion' => 'Cuentas por cobrar, pagar y conciliación.',
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
