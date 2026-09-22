<?php

return [
    'grupos' => [
        [
            'titulo' => 'Panel y administración',
            'enlaces' => [
                ['titulo' => 'Dashboard', 'ruta' => 'dashboard', 'permisos' => ['dashboard.ver'], 'detalle' => 'Verifica las tarjetas y la bandeja según tu perfil.'],
                ['titulo' => 'Usuarios', 'ruta' => 'usuarios.index', 'permisos' => ['usuarios.gestionar'], 'detalle' => 'Revisa listado y acciones visibles por permiso.'],
                ['titulo' => 'Empleados', 'ruta' => 'empleados.index', 'permisos' => ['empleados.gestionar'], 'detalle' => 'Revisa listado y formulario.'],
            ],
        ],
        [
            'titulo' => 'Clientes y órdenes',
            'enlaces' => [
                ['titulo' => 'Clientes', 'ruta' => 'clientes.index', 'permisos' => ['clientes.gestionar'], 'detalle' => 'Abre un cliente y revisa direcciones, contactos y vehículos.'],
                ['titulo' => 'Proformas', 'ruta' => 'proformas.index', 'permisos' => ['proformas.ver'], 'detalle' => 'Abre una proforma y comprueba sus tablas.'],
                ['titulo' => 'Cotizaciones de cliente', 'ruta' => 'cotizaciones-cliente.index', 'permisos' => ['proformas.ver'], 'detalle' => 'Abre un registro y recorre sus pestañas, presupuesto y versiones.'],
                ['titulo' => 'Nueva cotización', 'ruta' => 'cotizaciones-cliente.create', 'permisos' => ['proformas.cotizar'], 'detalle' => 'Revisa materiales, presupuesto y validación del formulario.'],
                ['titulo' => 'Órdenes de operación', 'ruta' => 'ordenes-operacion.index', 'permisos' => ['ordenes.ver'], 'detalle' => 'Abre una orden: ejecución, materiales, reservas, herramientas y abastecimiento.'],
                ['titulo' => 'Nueva orden', 'ruta' => 'ordenes-operacion.create', 'permisos' => ['ordenes.crear_comercial'], 'detalle' => 'Comprueba campos, ayudas y errores.'],
            ],
        ],
        [
            'titulo' => 'Compras y proveedores',
            'enlaces' => [
                ['titulo' => 'Proveedores', 'ruta' => 'proveedores.index', 'permisos' => ['proveedores.gestionar'], 'detalle' => 'Abre un proveedor y revisa datos, productos, documentos e historial.'],
                ['titulo' => 'Requerimientos', 'ruta' => 'requerimientos-compra.index', 'permisos' => ['requerimientos.compra.crear', 'requerimientos.compra.gestionar'], 'detalle' => 'Abre un requerimiento para revisar cotizaciones y comparativo.'],
                ['titulo' => 'Nuevo requerimiento', 'ruta' => 'requerimientos-compra.create', 'permisos' => ['requerimientos.compra.crear'], 'detalle' => 'Verifica captura y validaciones.'],
                ['titulo' => 'Cotizaciones de proveedor', 'ruta' => 'cotizaciones-proveedor.index', 'permisos' => ['compras.gestionar'], 'detalle' => 'Abre una cotización y revisa detalle y documentos.'],
                ['titulo' => 'Órdenes de compra', 'ruta' => 'ordenes-compra.index', 'permisos' => ['compras.gestionar', 'contabilidad.ver', 'ingresos.ver'], 'detalle' => 'Abre una OC y comprueba productos y saldo por recibir.'],
                ['titulo' => 'Facturas de proveedor', 'ruta' => 'facturas-proveedor.index', 'permisos' => ['compras.gestionar', 'contabilidad.ver', 'ingresos.ver'], 'detalle' => 'Abre una factura y revisa comprobante y conciliación.'],
            ],
        ],
        [
            'titulo' => 'Inventario y almacén',
            'enlaces' => [
                ['titulo' => 'Productos', 'ruta' => 'productos.index', 'permisos' => ['productos.ver'], 'detalle' => 'Abre un producto y revisa existencias, ubicaciones y presentaciones.'],
                ['titulo' => 'Nuevo producto', 'ruta' => 'productos.create', 'permisos' => ['productos.gestionar'], 'detalle' => 'Verifica presentaciones y errores de validación.'],
                ['titulo' => 'Inventario', 'ruta' => 'inventario.index', 'permisos' => ['inventario.ver'], 'detalle' => 'Revisa disponibilidad, filtros y tablas.'],
                ['titulo' => 'Repisas', 'ruta' => 'repisas.index', 'permisos' => ['repisas.ver'], 'detalle' => 'Revisa ubicaciones y estados vacíos.'],
                ['titulo' => 'Alertas', 'ruta' => 'alertas.index', 'permisos' => ['alertas.ver'], 'detalle' => 'Prueba filtros y enlace al requerimiento.'],
                ['titulo' => 'Notas de ingreso', 'ruta' => 'notas-ingreso.index', 'permisos' => ['ingresos.ver', 'contabilidad.ver'], 'detalle' => 'Abre una nota y comprueba ubicación y trazabilidad.'],
                ['titulo' => 'Nueva nota de ingreso', 'ruta' => 'notas-ingreso.create', 'permisos' => ['ingresos.registrar'], 'detalle' => 'Recorre los pasos del formulario.'],
                ['titulo' => 'Notas de salida', 'ruta' => 'notas-salida.index', 'permisos' => ['salidas.listar'], 'detalle' => 'Abre una nota y revisa materiales y devoluciones.'],
                ['titulo' => 'Nueva nota de salida', 'ruta' => 'notas-salida.create', 'permisos' => ['salidas.registrar'], 'detalle' => 'Recorre los pasos del formulario y la validación de stock.'],
                ['titulo' => 'Movimientos', 'ruta' => 'movimientos.index', 'permisos' => ['movimientos.ver'], 'detalle' => 'Revisa filtros y detalle expandible.'],
                ['titulo' => 'Kardex', 'ruta' => 'kardex.index', 'permisos' => ['kardex.ver'], 'detalle' => 'Comprueba filtros y valorización.'],
                ['titulo' => 'Inventarios periódicos', 'ruta' => 'inventarios-periodicos.index', 'permisos' => ['inventario.ver'], 'detalle' => 'Abre un conteo y revisa el detalle.'],
            ],
        ],
    ],
];
