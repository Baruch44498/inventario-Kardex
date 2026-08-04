<?php

namespace App\Support;

final class PermisoSistema
{
    public const DASHBOARD_VER = 'dashboard.ver';

    public const PRODUCTOS_VER = 'productos.ver';
    public const PRODUCTOS_GESTIONAR = 'productos.gestionar';

    public const INVENTARIO_VER = 'inventario.ver';
    public const INVENTARIO_CONFIGURAR = 'inventario.configurar';

    public const REPISAS_VER = 'repisas.ver';
    public const REPISAS_GESTIONAR = 'repisas.gestionar';

    public const MOVIMIENTOS_VER = 'movimientos.ver';

    public const INGRESOS_VER = 'ingresos.ver';
    public const INGRESOS_REGISTRAR = 'ingresos.registrar';

    public const SALIDAS_LISTAR = 'salidas.listar';
    public const SALIDAS_VER = 'salidas.ver';
    public const SALIDAS_REGISTRAR = 'salidas.registrar';
    public const SALIDAS_ANULAR = 'salidas.anular';

    public const ALERTAS_VER = 'alertas.ver';
    public const ALERTAS_GESTIONAR = 'alertas.gestionar';

    public const ORDENES_VER = 'ordenes.ver';
    public const ORDENES_CREAR_COMERCIAL = 'ordenes.crear_comercial';
    public const ORDENES_CREAR_VENTA = 'ordenes.crear_venta';
    public const ORDENES_EDITAR_COMERCIAL = 'ordenes.editar_comercial';
    public const ORDENES_EDITAR_VENTA = 'ordenes.editar_venta';
    public const ORDENES_ANULAR_COMERCIAL = 'ordenes.anular_comercial';
    public const ORDENES_ANULAR_VENTA = 'ordenes.anular_venta';
    public const ORDENES_GESTIONAR_ESTADO = 'ordenes.gestionar_estado';

    public const CLIENTES_GESTIONAR = 'clientes.gestionar';
    public const PROVEEDORES_GESTIONAR = 'proveedores.gestionar';
    public const COMPRAS_GESTIONAR = 'compras.gestionar';
    public const PROFORMAS_VER = 'proformas.ver';
    public const PROFORMAS_CREAR = 'proformas.crear';
    public const PROFORMAS_COTIZAR = 'proformas.cotizar';
    public const PROFORMAS_GESTIONAR = 'proformas.gestionar';

    public const PRODUCCION_VER = 'produccion.ver';
    public const PRODUCCION_GESTIONAR = 'produccion.gestionar';

    public const CONTABILIDAD_VER = 'contabilidad.ver';

    public const KARDEX_VER = 'kardex.ver';
    public const AUDITORIA_VER = 'auditoria.ver';
    public const USUARIOS_GESTIONAR = 'usuarios.gestionar';

    private function __construct() {}
}
