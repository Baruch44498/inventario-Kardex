<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $ahora = now();

            $rolAnterior = DB::table('roles')
                ->where('codigo', 'JEFE_COMPRAS')
                ->first();

            $rolComercial = DB::table('roles')
                ->where('codigo', 'COMERCIAL_LOGISTICA')
                ->first();

            if ($rolAnterior && $rolComercial) {
                DB::table('users')
                    ->where('role_id', $rolAnterior->id)
                    ->update(['role_id' => $rolComercial->id]);

                DB::table('roles')
                    ->where('id', $rolAnterior->id)
                    ->delete();
            } elseif ($rolAnterior) {
                DB::table('roles')
                    ->where('id', $rolAnterior->id)
                    ->update([
                        'codigo' => 'COMERCIAL_LOGISTICA',
                        'nombre' => 'Comercial y logística',
                        'descripcion' => 'Clientes, proveedores, compras, proformas y órdenes comerciales.',
                        'estado' => true,
                        'updated_at' => $ahora,
                    ]);
            }

            $roles = [
                [
                    'codigo' => 'ADMINISTRADOR',
                    'nombre' => 'Administrador',
                    'descripcion' => 'Acceso total, supervisión, Kardex, auditoría y usuarios.',
                ],
                [
                    'codigo' => 'COMERCIAL_LOGISTICA',
                    'nombre' => 'Comercial y logística',
                    'descripcion' => 'Clientes, proveedores, compras, proformas y órdenes OP, OM y OS.',
                ],
                [
                    'codigo' => 'ALMACEN',
                    'nombre' => 'Almacén',
                    'descripcion' => 'Inventario, ingresos, salidas, alertas y ventas directas OV.',
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
                $existente = DB::table('roles')
                    ->where('codigo', $rol['codigo'])
                    ->first();

                if ($existente) {
                    DB::table('roles')
                        ->where('id', $existente->id)
                        ->update([
                            ...$rol,
                            'estado' => true,
                            'updated_at' => $ahora,
                        ]);
                } else {
                    DB::table('roles')->insert([
                        ...$rol,
                        'estado' => true,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $ahora = now();

            $comercial = DB::table('roles')
                ->where('codigo', 'COMERCIAL_LOGISTICA')
                ->first();

            $anterior = DB::table('roles')
                ->where('codigo', 'JEFE_COMPRAS')
                ->first();

            if ($comercial && ! $anterior) {
                DB::table('roles')
                    ->where('id', $comercial->id)
                    ->update([
                        'codigo' => 'JEFE_COMPRAS',
                        'nombre' => 'Jefe de compras',
                        'descripcion' => 'Proveedores, solicitudes de compra y cotizaciones.',
                        'updated_at' => $ahora,
                    ]);
            }

            DB::table('roles')
                ->whereIn('codigo', ['JEFE_PLANTA', 'CONTABILIDAD'])
                ->update([
                    'estado' => false,
                    'updated_at' => $ahora,
                ]);
        });
    }
};
