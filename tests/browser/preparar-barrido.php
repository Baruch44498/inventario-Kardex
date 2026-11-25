<?php

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\CotizacionCliente;
use App\Models\CotizacionPresupuesto;
use App\Models\Empleado;
use App\Models\FacturaProveedor;
use App\Models\ImportacionPlantillaCosteo;
use App\Models\MovimientoInventario;
use App\Models\NotaIngreso;
use App\Models\NotaSalida;
use App\Models\OrdenOperacion;
use App\Models\PlantillaCosteo;
use App\Models\Proforma;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Inventario\InventarioPeriodicoService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

$usuarios = ['ALMACEN' => $usuario];
$sesiones = ['ALMACEN' => ['email' => $usuario->email, 'clave' => $clave]];
foreach (Role::query()->whereIn('codigo', array_keys(config('hidroil_permisos.roles')))->orderBy('codigo')->get() as $rol) {
    if (isset($usuarios[$rol->codigo])) {
        continue;
    }

    $alias = Str::slug($rol->codigo);
    $claveRol = Str::random(32);
    $usuarioRol = User::query()->create([
        'role_id' => $rol->id,
        'username' => $alias.'_auditoria',
        'email' => $alias.'-auditoria@example.test',
        'password' => $claveRol,
        'estado' => true,
        'es_administrador_principal' => $rol->codigo === 'ADMINISTRADOR',
        'fecha_creacion' => now(),
    ]);
    $usuarios[$rol->codigo] = $usuarioRol;
    $sesiones[$rol->codigo] = ['email' => $usuarioRol->email, 'clave' => $claveRol];
}

$logistica = $usuarios['COMERCIAL_LOGISTICA'];
$tipoCliente = TipoCliente::query()->create([
    'codigo' => 'QA', 'nombre' => 'Cliente de prueba', 'porcentaje_ganancia' => 15, 'estado' => true,
]);
$cliente = Cliente::query()->create([
    'tipo_cliente_id' => $tipoCliente->id, 'tipo_documento' => 'RUC',
    'numero_documento' => '20999999888', 'ruc' => '20999999888',
    'razon_social' => 'Servicios industriales de prueba de auditoría S.A.C.', 'estado' => true,
]);
$direccion = ClienteDireccion::query()->create([
    'cliente_id' => $cliente->id, 'direccion' => 'Avenida de prueba 180',
    'destino' => 'Taller principal', 'es_principal' => true, 'estado' => true,
]);
$vehiculo = Vehiculo::query()->create([
    'cliente_id' => $cliente->id, 'placa' => 'QA1-001', 'marca' => 'Marca de prueba',
    'modelo' => 'Camión industrial', 'estado' => true,
]);
$empleado = Empleado::query()->create([
    'nombre_completo' => 'Operador de prueba de almacén', 'dni' => '70999999',
    'estado' => true, 'registrado_por' => $usuario->id,
]);
$usuario->update(['empleado_id' => $empleado->id]);

$tipoOrden = TipoOrden::query()->where('codigo', 'OP')->firstOrFail();
$proforma = Proforma::query()->create([
    'cliente_id' => $cliente->id, 'codigo' => 'PRO-UI-01', 'fecha_emision' => today(),
    'moneda' => 'PEN', 'tipo_cambio' => 1, 'estado' => 'BORRADOR', 'registrado_por' => $logistica->id,
]);
$proforma->detalles()->create([
    'producto_id' => $producto->id, 'codigo_producto' => $producto->codigo,
    'descripcion' => $producto->descripcion, 'unidad_medida' => 'UND', 'cantidad' => 2,
    'tratamiento' => 'VENTA', 'precio_unitario' => 0, 'subtotal' => 0, 'impuesto' => 0, 'total' => 0,
]);
$cotizacionCliente = CotizacionCliente::query()->create([
    'origen' => 'DIRECTA_LOGISTICA', 'tipo_orden_id' => $tipoOrden->id,
    'cliente_id' => $cliente->id, 'codigo' => 'COT-UI-01', 'codigo_base' => 'COT-UI-01', 'version' => 1,
    'fecha_emision' => today(), 'cliente_documento' => $cliente->ruc, 'cliente_nombre' => $cliente->razon_social,
    'moneda' => 'PEN', 'tipo_cambio' => 1, 'subtotal' => 100, 'impuesto' => 18, 'total' => 118,
    'estado' => 'ABIERTA', 'cotizado_por' => $logistica->id,
]);
$cotizacionCliente->detalles()->create([
    'producto_id' => $producto->id, 'codigo_producto' => $producto->codigo,
    'descripcion' => $producto->descripcion, 'unidad_medida' => 'UND',
    'cantidad' => 10, 'precio_unitario' => 10, 'subtotal' => 100, 'impuesto' => 18, 'total' => 118,
]);
$cotizacionCerrada = $cotizacionCliente->replicate()->fill([
    'codigo' => 'COT-UI-02', 'codigo_base' => 'COT-UI-02', 'estado' => 'CERRADA',
    'proforma_id' => $proforma->id, 'origen' => 'PROFORMA',
]);
$cotizacionCerrada->save();
$presupuesto = CotizacionPresupuesto::query()->create([
    'cotizacion_cliente_id' => $cotizacionCliente->id, 'producto_id' => $producto->id,
    'tipo_costo' => 'MATERIAL', 'descripcion' => $producto->descripcion, 'cantidad' => 2, 'unidad' => 'UND',
    'moneda' => 'PEN', 'tipo_cambio' => 1, 'costo_unitario' => 5, 'estado' => 'VIGENTE',
    'costo_neto_original' => 10, 'costo_total_original' => 10,
    'costo_neto_soles' => 10, 'costo_total_soles' => 10, 'costo_neto_dolares' => 10, 'costo_total_dolares' => 10,
    'registrado_por' => $logistica->id, 'registrado_en' => now(),
]);
$plantilla = PlantillaCosteo::query()->create([
    'tipo_orden_id' => $tipoOrden->id, 'nombre' => 'Plantilla de servicio de prueba',
    'activo' => true, 'creado_por' => $logistica->id,
]);
$importacion = ImportacionPlantillaCosteo::query()->create([
    'tipo_orden_id' => $tipoOrden->id, 'nombre' => 'Importación de prueba',
    'nombre_original' => 'auditoria.xlsx', 'ruta_archivo' => 'auditoria/inexistente.xlsx',
    'estado' => 'BORRADOR', 'creado_por' => $logistica->id,
]);
$operacion = OrdenOperacion::query()->create([
    'tipo_orden_id' => $tipoOrden->id, 'cliente_id' => $cliente->id,
    'codigo_orden' => 'OP-UI-01', 'numero_correlativo' => 1, 'anio' => now()->year,
    'fecha_apertura' => today(), 'descripcion' => 'Mantenimiento industrial para auditoría móvil',
    'estado' => 'EN_PROCESO', 'creado_por' => $logistica->id,
]);

$requerimiento = Requisicion::query()->create([
    'codigo' => 'REQ-UI-01', 'fecha_solicitud' => today(), 'origen' => 'REPOSICION',
    'descripcion' => 'Reposición ficticia de materiales para auditoría móvil',
    'prioridad' => 'ALTA', 'estado' => 'COTIZANDO', 'estado_abastecimiento' => 'PENDIENTE',
    'solicitado_por' => $usuario->id, 'enviado_por' => $usuario->id, 'enviado_en' => now(),
]);
$lineaRequerida = $requerimiento->detalles()->create([
    'producto_id' => $producto->id, 'cantidad_solicitada' => 10,
]);
$borrador = $requerimiento->replicate()->fill([
    'codigo' => 'REQ-UI-02', 'estado' => 'BORRADOR', 'enviado_por' => null, 'enviado_en' => null,
]);
$borrador->save();
$borrador->detalles()->create(['producto_id' => $producto->id, 'cantidad_solicitada' => 4]);

$ofertaEditable = $cotizacion->replicate()->fill([
    'codigo' => 'CP-UI-02', 'numero_documento' => 'DOC-UI-02', 'estado' => 'REGISTRADA',
    'requisicion_id' => $requerimiento->id, 'registrado_por' => $logistica->id,
]);
$ofertaEditable->save();
$ofertaEditable->detalles()->save($oferta->replicate()->fill([
    'requisicion_detalle_id' => $lineaRequerida->id, 'tipo_vinculacion' => 'SOLICITADO',
]));
$cotizacionAprobada = $cotizacion->replicate()->fill([
    'codigo' => 'CP-UI-03', 'numero_documento' => 'DOC-UI-03',
]);
$cotizacionAprobada->save();
$ofertaAprobada = $cotizacionAprobada->detalles()->save($oferta->replicate());
$solicitudAprobada = $solicitud->replicate()->fill([
    'codigo' => 'SC-UI-02', 'cotizacion_id' => $cotizacionAprobada->id, 'estado' => 'APROBADA',
]);
$solicitudAprobada->save();
$solicitudAprobada->detalles()->save($detalle->replicate()->fill(['cotizacion_detalle_id' => $ofertaAprobada->id]));

$ingreso = NotaIngreso::query()->create([
    'orden_compra_id' => $orden->id, 'codigo' => 'NI-UI-01', 'fecha_ingreso' => today(),
    'motivo_ingreso' => 'COMPRA', 'estado' => 'CONFIRMADA', 'registrado_por' => $usuario->id,
]);
$lineaIngreso = $ingreso->detalles()->create([
    'orden_compra_detalle_id' => $lineaOrden->id, 'producto_id' => $producto->id,
    'repisa_id' => $repisa->id, 'cantidad' => 4, 'costo_unitario' => 11.8, 'subtotal' => 47.2,
    'afecta_stock' => true,
]);
$lineaOrden->update(['cantidad_recibida' => 4]);
$salida = NotaSalida::query()->create([
    'orden_operacion_id' => $operacion->id, 'codigo' => 'NS-UI-01', 'fecha_salida' => today(),
    'estado' => 'CONFIRMADA', 'registrado_por' => $usuario->id,
]);
$salida->detalles()->create([
    'producto_id' => $producto->id, 'repisa_id' => $repisa->id, 'cantidad' => 2,
    'costo_unitario_promedio' => 5, 'subtotal' => 10, 'tratamiento' => 'CONSUMO',
]);
$movimiento = MovimientoInventario::query()->create([
    'inventario_id' => $inventario->id, 'producto_id' => $producto->id, 'repisa_id' => $repisa->id,
    'tipo_movimiento' => 'ENTRADA', 'motivo' => 'COMPRA', 'origen_tipo' => 'NOTA_INGRESO',
    'origen_id' => $ingreso->id, 'cantidad' => 4, 'stock_anterior' => 6, 'stock_posterior' => 10,
    'costo_unitario' => 11.8, 'costo_promedio_anterior' => 5, 'costo_promedio_nuevo' => 5,
    'fecha_movimiento' => now(), 'registrado_por' => $usuario->id,
]);
$factura = FacturaProveedor::query()->create([
    'orden_compra_id' => $orden->id, 'proveedor_id' => $proveedor->id,
    'tipo_documento' => 'FACTURA', 'serie' => 'F001', 'numero' => '9991', 'fecha_emision' => today(),
    'fecha_vencimiento' => today()->addDays(15), 'moneda' => 'PEN', 'total' => 23.6,
    'registrado_por' => $usuario->id, 'estado' => 'REGISTRADA',
]);
$factura->detalles()->create([
    'orden_compra_detalle_id' => $lineaOrden->id, 'nota_ingreso_detalle_id' => $lineaIngreso->id,
    'producto_id' => $producto->id, 'descripcion' => $producto->descripcion, 'cantidad' => 2,
    'precio_unitario' => 10, 'igv_porcentaje' => 18, 'subtotal' => 20, 'impuesto' => 3.6, 'total' => 23.6,
]);
$repisaConteo = $repisa->replicate()->fill(['codigo' => 'QA002', 'descripcion' => 'Repisa de prueba con conteo abierto']);
$repisaConteo->save();
$inventario->replicate()->fill(['repisa_id' => $repisaConteo->id])->save();
$inventarioPeriodico = app(InventarioPeriodicoService::class)->abrir(['repisa_id' => $repisaConteo->id], $usuario);

$repisaSalida = $repisa->replicate()->fill(['codigo' => 'QA015', 'descripcion' => 'Repisa para pruebas de salida']);
$repisaSalida->save();
foreach (range(1, 11) as $numero) {
    $productoSalida = $producto->replicate()->fill([
        'codigo' => sprintf('UI-SALIDA-%02d', $numero),
        'descripcion' => 'Válvula de prueba '.$numero,
    ]);
    $productoSalida->save();
    $inventario->replicate()->fill(['producto_id' => $productoSalida->id, 'repisa_id' => $repisaSalida->id])->save();
}

$parametros = [
    'cliente' => $cliente, 'direccion' => $direccion, 'vehiculo' => $vehiculo,
    'proforma' => $proforma, 'cotizacionCliente' => $cotizacionCliente, 'presupuesto' => $presupuesto,
    'proveedor' => $proveedor, 'cotizacion' => $ofertaEditable, 'solicitudCompra' => $solicitud,
    'ordenCompra' => $orden, 'facturaProveedor' => $factura, 'requerimientoCompra' => $requerimiento,
    'producto' => $producto, 'inventario' => $inventario, 'repisa' => $repisa,
    'inventarioPeriodico' => $inventarioPeriodico, 'movimiento' => $movimiento,
    'notaIngreso' => $ingreso, 'notaSalida' => $salida, 'ordenOperacion' => $operacion,
    'usuario' => $usuario, 'empleado' => $empleado, 'plantilla' => $plantilla, 'importacion' => $importacion,
];

$variantes = [
    'ordenes-compra.create' => ['solicitudCompra' => $solicitudAprobada],
    'requerimientos-compra.edit' => ['requerimientoCompra' => $borrador],
    'cuentas-cobrar.show' => ['cotizacionCliente' => $cotizacionCerrada],
    'notas-ingreso.create' => ['orden_compra_id' => $orden->id],
];
$extras = [
    'cotizaciones-cliente.documento', 'ordenes-compra.documento',
    'ordenes-operacion.gasto-real', 'requerimientos-compra.comparativo',
];
$redirecciones = [
    'home', 'modulos.show', 'ordenes-operacion.create', 'cotizaciones-proveedor.importacion.reanudar',
    'cotizaciones-proveedor.importacion.create',
];
$pantallas = [];
$exclusiones = [];
foreach (Route::getRoutes() as $ruta) {
    $nombre = $ruta->getName();
    if (! $nombre || ! in_array('GET', $ruta->methods(), true) || ! in_array('web', $ruta->gatherMiddleware(), true)) {
        continue;
    }

    if (in_array($nombre, $redirecciones, true)) {
        $exclusiones[$nombre] = 'Redirige a una pantalla incluida en el barrido.';
        continue;
    }
    if ($nombre !== 'login' && $nombre !== 'dashboard'
        && ! in_array(Str::afterLast($nombre, '.'), ['index', 'create', 'show', 'edit'], true)
        && ! in_array($nombre, $extras, true)) {
        $exclusiones[$nombre] = 'Endpoint sin pantalla en el catálogo de auditoría.';
        continue;
    }

    $valores = $variantes[$nombre] ?? [];
    foreach ($ruta->parameterNames() as $parametro) {
        if (! isset($valores[$parametro]) && isset($parametros[$parametro])) {
            $valores[$parametro] = $parametros[$parametro];
        }
    }
    if (array_diff($ruta->parameterNames(), array_keys($valores))) {
        $exclusiones[$nombre] = 'Requiere un registro de prueba adicional.';
        continue;
    }

    $roles = [];
    foreach ($usuarios as $codigo => $perfil) {
        $autorizado = $nombre !== 'login';
        foreach ($ruta->gatherMiddleware() as $middleware) {
            if (Str::startsWith($middleware, 'permiso:')) {
                $autorizado = $autorizado && $perfil->puedeAlguno(...explode(',', Str::after($middleware, ':')));
            } elseif (Str::startsWith($middleware, 'rol:')) {
                $autorizado = $autorizado && $perfil->tieneRol(...explode(',', Str::after($middleware, ':')));
            }
        }
        if ($autorizado) {
            $roles[] = $codigo;
        }
    }
    $pantallas[$nombre] = [
        'ruta' => route($nombre, $valores, false),
        'roles' => $nombre === 'login' ? ['INVITADO'] : $roles,
    ];
}
$estadosIniciales = [
    'registro-almacen.detalle' => ['registro-almacen.index', ['q' => $producto->codigo, 'producto' => $producto->id]],
    'cotizaciones-proveedor.importacion.formulario' => ['cotizaciones-proveedor.create', ['modo' => 'importar']],
    'notas-ingreso.sin-orden' => ['notas-ingreso.create', []],
    'notas-salida.entrega-personal' => ['notas-salida.create', ['motivo_salida' => 'USO_INTERNO']],
    'notas-salida.con-orden' => ['notas-salida.create', ['orden_operacion_id' => $operacion->id]],
];
foreach ($estadosIniciales as $nombre => [$ruta, $valores]) {
    $pantallas[$nombre] = [
        'ruta' => route($ruta, $valores, false),
        'roles' => $pantallas[$ruta]['roles'],
    ];
}
ksort($pantallas);
ksort($exclusiones);

$archivoBarrido = $directorio.'/barrido.json';
file_put_contents($archivoBarrido, json_encode([
    'sesiones' => $sesiones, 'pantallas' => $pantallas, 'exclusiones' => $exclusiones,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
chmod($archivoBarrido, 0600);
