<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuloPlaceholderController extends Controller
{
    private const MODULOS = [
        'productos' => ['Productos', 'products'],
        'inventario' => ['Inventario', 'inventory'],
        'movimientos' => ['Movimientos de inventario', 'movements'],
        'entradas' => ['Notas de ingreso', 'entry'],
        'salidas' => ['Notas de salida', 'exit'],
        'alertas' => ['Alertas de stock', 'alerts'],
        'ordenes' => ['Órdenes de operación', 'orders'],
        'proveedores' => ['Proveedores', 'suppliers'],
        'requisiciones' => ['Requisiciones', 'requisitions'],
        'cotizaciones' => ['Cotizaciones', 'quotes'],
        'solicitudes-compra' => ['Solicitudes de compra', 'purchase-request'],
        'ordenes-compra' => ['Órdenes de compra', 'purchase-order'],
        'facturas' => ['Facturas de proveedor', 'invoice'],
        'usuarios' => ['Usuarios y roles', 'users'],
    ];

    public function show(Request $request, string $modulo): View
    {
        abort_unless(array_key_exists($modulo, self::MODULOS), 404);

        [$nombreModulo, $iconoModulo] = self::MODULOS[$modulo];

        return view('modulos.proximamente', compact(
            'modulo',
            'nombreModulo',
            'iconoModulo'
        ));
    }
}
