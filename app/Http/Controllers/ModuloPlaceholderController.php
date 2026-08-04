<?php

namespace App\Http\Controllers;

use App\Support\PermisoSistema as P;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuloPlaceholderController extends Controller
{
    private const MODULOS = [
        'clientes' => ['Clientes', P::CLIENTES_GESTIONAR],
        'proveedores' => ['Proveedores', P::PROVEEDORES_GESTIONAR],
        'requisiciones' => ['Requisiciones', P::COMPRAS_GESTIONAR],
        'cotizaciones' => ['Cotizaciones de proveedores', P::COMPRAS_GESTIONAR],
        'solicitudes-compra' => ['Solicitudes de compra', P::COMPRAS_GESTIONAR],
        'ordenes-compra' => ['Órdenes de compra', P::COMPRAS_GESTIONAR],
        'facturas' => ['Facturas de proveedor', P::COMPRAS_GESTIONAR],
        'proformas' => ['Proformas', P::PROFORMAS_GESTIONAR],
        'produccion' => ['Seguimiento de producción', P::PRODUCCION_VER],
        'cuentas-cobrar' => ['Cuentas por cobrar', P::CONTABILIDAD_VER],
        'cuentas-pagar' => ['Cuentas por pagar', P::CONTABILIDAD_VER],
        'kardex' => ['Kardex valorizado', P::KARDEX_VER],
        'auditoria' => ['Auditoría del sistema', P::AUDITORIA_VER],
    ];

    public function show(
        Request $request,
        string $modulo
    ): View|RedirectResponse {
        if ($modulo === 'proveedores') {
            return redirect()->route('proveedores.index');
        }

        if ($modulo === 'cotizaciones') {
            return redirect()->route('cotizaciones-proveedor.index');
        }

        abort_unless(array_key_exists($modulo, self::MODULOS), 404);

        [$nombre, $permiso] = self::MODULOS[$modulo];

        abort_unless(
            $request->user()?->puede($permiso),
            403,
            'Tu perfil no tiene acceso a este módulo.'
        );

        return view('modulos.proximamente', [
            'nombreModulo' => $nombre,
        ]);
    }
}
