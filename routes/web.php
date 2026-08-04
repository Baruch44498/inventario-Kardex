<?php

use App\Http\Controllers\AlertaStockController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CatalogoBusquedaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ClienteDireccionController;
use App\Http\Controllers\CotizacionProveedorController;
use App\Http\Controllers\CotizacionClienteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistorialPrecioProveedorController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\ModuloPlaceholderController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Controllers\NotaIngresoController;
use App\Http\Controllers\NotaSalidaController;
use App\Http\Controllers\OrdenOperacionController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProformaController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\RepisaController;
use App\Http\Controllers\TipoClienteController;
use App\Http\Controllers\VehiculoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'usuario.activo'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permiso:dashboard.ver')
        ->name('dashboard');

    Route::prefix('catalogos')->name('catalogos.')->group(function () {
        Route::get('/proveedores/buscar', [CatalogoBusquedaController::class, 'proveedores'])
            ->middleware('permiso:compras.gestionar')
            ->name('proveedores.buscar');
        Route::get('/productos/buscar', [CatalogoBusquedaController::class, 'productos'])
            ->middleware('permiso:compras.gestionar,productos.ver,productos.gestionar,proformas.crear,proformas.cotizar')
            ->name('productos.buscar');
        Route::get('/clientes/buscar', [CatalogoBusquedaController::class, 'clientes'])
            ->middleware('permiso:ordenes.crear_comercial,ordenes.crear_venta,ordenes.editar_comercial,ordenes.editar_venta,proformas.crear,proformas.cotizar')
            ->name('clientes.buscar');
        Route::get('/clientes/relaciones', [CatalogoBusquedaController::class, 'relacionesCliente'])
            ->middleware('permiso:ordenes.crear_comercial,ordenes.crear_venta,ordenes.editar_comercial,ordenes.editar_venta')
            ->name('clientes.relaciones');
        Route::get('/requisiciones/buscar', [CatalogoBusquedaController::class, 'requisiciones'])
            ->middleware('permiso:compras.gestionar')
            ->name('requisiciones.buscar');
        Route::get('/ordenes-compra/buscar', [CatalogoBusquedaController::class, 'ordenesCompra'])
            ->middleware('permiso:ingresos.registrar')
            ->name('ordenes-compra.buscar');
        Route::get('/ordenes-operacion/buscar', [CatalogoBusquedaController::class, 'ordenesOperacion'])
            ->middleware('permiso:salidas.registrar')
            ->name('ordenes-operacion.buscar');
        Route::get('/repisas/buscar', [CatalogoBusquedaController::class, 'repisas'])
            ->middleware('permiso:inventario.ver,ingresos.registrar')
            ->name('repisas.buscar');
        Route::get('/marcas/buscar', [CatalogoBusquedaController::class, 'marcas'])
            ->middleware('permiso:compras.gestionar,productos.ver,productos.gestionar')
            ->name('marcas.buscar');
    });

    Route::middleware('permiso:proformas.ver')->group(function () {
        Route::get('/proformas', [ProformaController::class, 'index'])
            ->name('proformas.index');
        Route::get('/proformas/{proforma}', [ProformaController::class, 'show'])
            ->whereNumber('proforma')
            ->name('proformas.show');
        Route::get('/cotizaciones-cliente', [CotizacionClienteController::class, 'index'])
            ->name('cotizaciones-cliente.index');
        Route::get('/cotizaciones-cliente/{cotizacionCliente}', [CotizacionClienteController::class, 'show'])
            ->whereNumber('cotizacionCliente')
            ->name('cotizaciones-cliente.show');
    });

    Route::middleware('permiso:proformas.crear')->group(function () {
        Route::get('/proformas/crear/nueva', [ProformaController::class, 'create'])
            ->name('proformas.create');
        Route::post('/proformas', [ProformaController::class, 'store'])
            ->name('proformas.store');
        Route::get('/proformas/{proforma}/editar', [ProformaController::class, 'edit'])
            ->whereNumber('proforma')
            ->name('proformas.edit');
        Route::put('/proformas/{proforma}', [ProformaController::class, 'update'])
            ->whereNumber('proforma')
            ->name('proformas.update');
        Route::patch('/proformas/{proforma}/enviar', [ProformaController::class, 'enviar'])
            ->whereNumber('proforma')
            ->name('proformas.enviar');
    });

    Route::middleware('permiso:proformas.cotizar')->group(function () {
        Route::get('/cotizaciones-cliente/crear/nueva', [CotizacionClienteController::class, 'create'])
            ->name('cotizaciones-cliente.create');
        Route::post('/cotizaciones-cliente', [CotizacionClienteController::class, 'storeDirecta'])
            ->name('cotizaciones-cliente.store');
        Route::post('/proformas/{proforma}/cotizar', [CotizacionClienteController::class, 'store'])
            ->whereNumber('proforma')
            ->name('proformas.cotizar');
        Route::patch('/proformas/{proforma}/anular', [ProformaController::class, 'anular'])
            ->whereNumber('proforma')
            ->name('proformas.anular');
        Route::get('/cotizaciones-cliente/{cotizacionCliente}/editar', [CotizacionClienteController::class, 'edit'])
            ->whereNumber('cotizacionCliente')
            ->name('cotizaciones-cliente.edit');
        Route::put('/cotizaciones-cliente/{cotizacionCliente}', [CotizacionClienteController::class, 'update'])
            ->whereNumber('cotizacionCliente')
            ->name('cotizaciones-cliente.update');
        Route::patch('/cotizaciones-cliente/{cotizacionCliente}/cerrar', [CotizacionClienteController::class, 'cerrar'])
            ->whereNumber('cotizacionCliente')
            ->name('cotizaciones-cliente.cerrar');
        Route::post('/cotizaciones-cliente/{cotizacionCliente}/version', [CotizacionClienteController::class, 'nuevaVersion'])
            ->whereNumber('cotizacionCliente')
            ->name('cotizaciones-cliente.version');
        Route::patch('/cotizaciones-cliente/{cotizacionCliente}/anular', [CotizacionClienteController::class, 'anular'])
            ->whereNumber('cotizacionCliente')
            ->name('cotizaciones-cliente.anular');
        Route::post('/cotizaciones-cliente/{cotizacionCliente}/convertir-orden', [CotizacionClienteController::class, 'convertirEnOrden'])
            ->whereNumber('cotizacionCliente')
            ->name('cotizaciones-cliente.convertir-orden');
    });

    Route::middleware('permiso:clientes.gestionar')->group(function () {
        Route::get('/clientes', [ClienteController::class, 'index'])
            ->name('clientes.index');
        Route::get('/clientes/crear', [ClienteController::class, 'create'])
            ->name('clientes.create');
        Route::post('/clientes', [ClienteController::class, 'store'])
            ->name('clientes.store');
        Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])
            ->whereNumber('cliente')
            ->name('clientes.show');
        Route::get('/clientes/{cliente}/editar', [ClienteController::class, 'edit'])
            ->whereNumber('cliente')
            ->name('clientes.edit');
        Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])
            ->whereNumber('cliente')
            ->name('clientes.update');
        Route::patch('/clientes/{cliente}/estado', [ClienteController::class, 'toggle'])
            ->whereNumber('cliente')
            ->name('clientes.toggle');

        Route::get('/clientes/{cliente}/direcciones/crear', [ClienteDireccionController::class, 'create'])
            ->whereNumber('cliente')
            ->name('clientes.direcciones.create');
        Route::post('/clientes/{cliente}/direcciones', [ClienteDireccionController::class, 'store'])
            ->whereNumber('cliente')
            ->name('clientes.direcciones.store');
        Route::get('/clientes/{cliente}/direcciones/{direccion}/editar', [ClienteDireccionController::class, 'edit'])
            ->whereNumber(['cliente', 'direccion'])
            ->name('clientes.direcciones.edit');
        Route::put('/clientes/{cliente}/direcciones/{direccion}', [ClienteDireccionController::class, 'update'])
            ->whereNumber(['cliente', 'direccion'])
            ->name('clientes.direcciones.update');
        Route::patch('/clientes/{cliente}/direcciones/{direccion}/estado', [ClienteDireccionController::class, 'toggle'])
            ->whereNumber(['cliente', 'direccion'])
            ->name('clientes.direcciones.toggle');
        Route::patch('/clientes/{cliente}/direcciones/{direccion}/principal', [ClienteDireccionController::class, 'principal'])
            ->whereNumber(['cliente', 'direccion'])
            ->name('clientes.direcciones.principal');

        Route::get('/clientes/{cliente}/vehiculos/crear', [VehiculoController::class, 'create'])
            ->whereNumber('cliente')
            ->name('clientes.vehiculos.create');
        Route::post('/clientes/{cliente}/vehiculos', [VehiculoController::class, 'store'])
            ->whereNumber('cliente')
            ->name('clientes.vehiculos.store');
        Route::get('/clientes/{cliente}/vehiculos/{vehiculo}', [VehiculoController::class, 'show'])
            ->whereNumber(['cliente', 'vehiculo'])
            ->name('clientes.vehiculos.show');
        Route::get('/clientes/{cliente}/vehiculos/{vehiculo}/editar', [VehiculoController::class, 'edit'])
            ->whereNumber(['cliente', 'vehiculo'])
            ->name('clientes.vehiculos.edit');
        Route::put('/clientes/{cliente}/vehiculos/{vehiculo}', [VehiculoController::class, 'update'])
            ->whereNumber(['cliente', 'vehiculo'])
            ->name('clientes.vehiculos.update');
        Route::patch('/clientes/{cliente}/vehiculos/{vehiculo}/estado', [VehiculoController::class, 'toggle'])
            ->whereNumber(['cliente', 'vehiculo'])
            ->name('clientes.vehiculos.toggle');

        Route::get('/tipos-cliente', [TipoClienteController::class, 'index'])
            ->name('tipos-cliente.index');
        Route::put('/tipos-cliente/{tipoCliente}', [TipoClienteController::class, 'update'])
            ->whereNumber('tipoCliente')
            ->name('tipos-cliente.update');
    });


    Route::middleware('permiso:proveedores.gestionar')->group(function () {
        Route::get('/proveedores', [ProveedorController::class, 'index'])
            ->name('proveedores.index');
        Route::get('/proveedores/crear', [ProveedorController::class, 'create'])
            ->name('proveedores.create');
        Route::post('/proveedores', [ProveedorController::class, 'store'])
            ->name('proveedores.store');
        Route::get('/proveedores/{proveedor}', [ProveedorController::class, 'show'])
            ->whereNumber('proveedor')
            ->name('proveedores.show');
        Route::get('/proveedores/{proveedor}/editar', [ProveedorController::class, 'edit'])
            ->whereNumber('proveedor')
            ->name('proveedores.edit');
        Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update'])
            ->whereNumber('proveedor')
            ->name('proveedores.update');
        Route::patch('/proveedores/{proveedor}/estado', [ProveedorController::class, 'toggle'])
            ->whereNumber('proveedor')
            ->name('proveedores.toggle');
    });

    Route::middleware('permiso:compras.gestionar')->group(function () {
        Route::get('/cotizaciones-proveedor', [CotizacionProveedorController::class, 'index'])
            ->name('cotizaciones-proveedor.index');
        Route::get('/cotizaciones-proveedor/crear', [CotizacionProveedorController::class, 'create'])
            ->name('cotizaciones-proveedor.create');
        Route::get('/cotizaciones-proveedor/productos/buscar', [CotizacionProveedorController::class, 'buscarProductos'])
            ->name('cotizaciones-proveedor.productos.buscar');
        Route::post('/cotizaciones-proveedor/productos/registro-rapido', [CotizacionProveedorController::class, 'registrarProductoRapido'])
            ->name('cotizaciones-proveedor.productos.registro-rapido');
        Route::post('/cotizaciones-proveedor', [CotizacionProveedorController::class, 'store'])
            ->name('cotizaciones-proveedor.store');
        Route::get('/cotizaciones-proveedor/{cotizacion}', [CotizacionProveedorController::class, 'show'])
            ->whereNumber('cotizacion')
            ->name('cotizaciones-proveedor.show');
        Route::get('/cotizaciones-proveedor/{cotizacion}/editar', [CotizacionProveedorController::class, 'edit'])
            ->whereNumber('cotizacion')
            ->name('cotizaciones-proveedor.edit');
        Route::put('/cotizaciones-proveedor/{cotizacion}', [CotizacionProveedorController::class, 'update'])
            ->whereNumber('cotizacion')
            ->name('cotizaciones-proveedor.update');
        Route::patch('/cotizaciones-proveedor/{cotizacion}/anular', [CotizacionProveedorController::class, 'anular'])
            ->whereNumber('cotizacion')
            ->name('cotizaciones-proveedor.anular');

        Route::get('/historial-precios-proveedores', [HistorialPrecioProveedorController::class, 'index'])
            ->name('historial-precios.index');
    });

    Route::middleware('permiso:productos.ver')->group(function () {
        Route::get('/productos', [ProductoController::class, 'index'])
            ->name('productos.index');
        Route::get('/productos/{producto}', [ProductoController::class, 'show'])
            ->whereNumber('producto')
            ->name('productos.show');
    });

    Route::middleware('permiso:productos.gestionar')->group(function () {
        Route::get('/productos/crear', [ProductoController::class, 'create'])
            ->name('productos.create');
        Route::post('/productos', [ProductoController::class, 'store'])
            ->name('productos.store');
        Route::get('/productos/{producto}/editar', [ProductoController::class, 'edit'])
            ->whereNumber('producto')
            ->name('productos.edit');
        Route::put('/productos/{producto}', [ProductoController::class, 'update'])
            ->whereNumber('producto')
            ->name('productos.update');
        Route::patch('/productos/{producto}/estado', [ProductoController::class, 'toggle'])
            ->whereNumber('producto')
            ->name('productos.toggle');
    });

    Route::get('/inventario', [InventarioController::class, 'index'])
        ->middleware('permiso:inventario.ver')
        ->name('inventario.index');

    Route::middleware('permiso:inventario.configurar')->group(function () {
        Route::get('/inventario/{inventario}/editar', [InventarioController::class, 'edit'])
            ->whereNumber('inventario')
            ->name('inventario.edit');
        Route::put('/inventario/{inventario}', [InventarioController::class, 'update'])
            ->whereNumber('inventario')
            ->name('inventario.update');
    });

    Route::get('/repisas', [RepisaController::class, 'index'])
        ->middleware('permiso:repisas.ver')
        ->name('repisas.index');

    Route::middleware('permiso:repisas.gestionar')->group(function () {
        Route::get('/repisas/crear', [RepisaController::class, 'create'])
            ->name('repisas.create');
        Route::post('/repisas', [RepisaController::class, 'store'])
            ->name('repisas.store');
        Route::get('/repisas/{repisa}/editar', [RepisaController::class, 'edit'])
            ->whereNumber('repisa')
            ->name('repisas.edit');
        Route::put('/repisas/{repisa}', [RepisaController::class, 'update'])
            ->whereNumber('repisa')
            ->name('repisas.update');
        Route::patch('/repisas/{repisa}/estado', [RepisaController::class, 'toggle'])
            ->whereNumber('repisa')
            ->name('repisas.toggle');
    });

    Route::middleware('permiso:movimientos.ver')->group(function () {
        Route::get('/movimientos', [MovimientoInventarioController::class, 'index'])
            ->name('movimientos.index');
        Route::get('/movimientos/{movimiento}', [MovimientoInventarioController::class, 'show'])
            ->whereNumber('movimiento')
            ->name('movimientos.show');
    });

    Route::get('/alertas-stock', [AlertaStockController::class, 'index'])
        ->middleware('permiso:alertas.ver')
        ->name('alertas.index');

    Route::middleware('permiso:alertas.gestionar')->group(function () {
        Route::post('/alertas-stock/evaluar', [AlertaStockController::class, 'evaluar'])
            ->name('alertas.evaluar');
        Route::patch('/alertas-stock/{alerta}/atender', [AlertaStockController::class, 'atender'])
            ->whereNumber('alerta')
            ->name('alertas.atender');
    });

    Route::middleware('permiso:ingresos.ver')->group(function () {
        Route::get('/notas-ingreso', [NotaIngresoController::class, 'index'])
            ->name('notas-ingreso.index');
        Route::get('/notas-ingreso/{notaIngreso}', [NotaIngresoController::class, 'show'])
            ->whereNumber('notaIngreso')
            ->name('notas-ingreso.show');
    });

    Route::middleware('permiso:ingresos.registrar')->group(function () {
        Route::get('/notas-ingreso/crear', [NotaIngresoController::class, 'create'])
            ->name('notas-ingreso.create');
        Route::post('/notas-ingreso', [NotaIngresoController::class, 'store'])
            ->name('notas-ingreso.store');
    });

    Route::get('/notas-salida', [NotaSalidaController::class, 'index'])
        ->middleware('permiso:salidas.listar')
        ->name('notas-salida.index');

    Route::get('/notas-salida/{notaSalida}', [NotaSalidaController::class, 'show'])
        ->middleware('permiso:salidas.ver')
        ->whereNumber('notaSalida')
        ->name('notas-salida.show');

    Route::middleware('permiso:salidas.registrar')->group(function () {
        Route::get('/notas-salida/crear', [NotaSalidaController::class, 'create'])
            ->name('notas-salida.create');
        Route::post('/notas-salida', [NotaSalidaController::class, 'store'])
            ->name('notas-salida.store');
    });

    Route::patch('/notas-salida/{notaSalida}/anular', [NotaSalidaController::class, 'anular'])
        ->middleware('permiso:salidas.anular')
        ->whereNumber('notaSalida')
        ->name('notas-salida.anular');

    Route::middleware('permiso:ordenes.ver')->group(function () {
        Route::get('/ordenes-operacion', [OrdenOperacionController::class, 'index'])
            ->name('ordenes-operacion.index');
        Route::get('/ordenes-operacion/{ordenOperacion}', [OrdenOperacionController::class, 'show'])
            ->whereNumber('ordenOperacion')
            ->name('ordenes-operacion.show');
    });

    Route::middleware('permiso:ordenes.crear_comercial,ordenes.crear_venta')->group(function () {
        Route::get('/ordenes-operacion/crear', [OrdenOperacionController::class, 'create'])
            ->name('ordenes-operacion.create');
        Route::post('/ordenes-operacion', [OrdenOperacionController::class, 'store'])
            ->name('ordenes-operacion.store');
    });

    Route::middleware('permiso:ordenes.editar_comercial,ordenes.editar_venta')->group(function () {
        Route::get('/ordenes-operacion/{ordenOperacion}/editar', [OrdenOperacionController::class, 'edit'])
            ->whereNumber('ordenOperacion')
            ->name('ordenes-operacion.edit');
        Route::put('/ordenes-operacion/{ordenOperacion}', [OrdenOperacionController::class, 'update'])
            ->whereNumber('ordenOperacion')
            ->name('ordenes-operacion.update');
    });

    Route::middleware('permiso:ordenes.gestionar_estado')->group(function () {
        Route::patch('/ordenes-operacion/{ordenOperacion}/iniciar', [OrdenOperacionController::class, 'iniciar'])
            ->whereNumber('ordenOperacion')
            ->name('ordenes-operacion.iniciar');
        Route::patch('/ordenes-operacion/{ordenOperacion}/cerrar', [OrdenOperacionController::class, 'cerrar'])
            ->whereNumber('ordenOperacion')
            ->name('ordenes-operacion.cerrar');
    });

    Route::patch('/ordenes-operacion/{ordenOperacion}/anular', [OrdenOperacionController::class, 'anular'])
        ->middleware('permiso:ordenes.anular_comercial,ordenes.anular_venta')
        ->whereNumber('ordenOperacion')
        ->name('ordenes-operacion.anular');

    Route::middleware('permiso:usuarios.gestionar')->group(function () {
        Route::get('/usuarios', [UsuarioController::class, 'index'])
            ->name('usuarios.index');
        Route::get('/usuarios/crear', [UsuarioController::class, 'create'])
            ->name('usuarios.create');
        Route::post('/usuarios', [UsuarioController::class, 'store'])
            ->name('usuarios.store');
        Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])
            ->whereNumber('usuario')
            ->name('usuarios.edit');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])
            ->whereNumber('usuario')
            ->name('usuarios.update');
        Route::patch('/usuarios/{usuario}/estado', [UsuarioController::class, 'toggle'])
            ->whereNumber('usuario')
            ->name('usuarios.toggle');
    });

    Route::get('/modulos/{modulo}', [ModuloPlaceholderController::class, 'show'])
        ->name('modulos.show');

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('logout');
});
