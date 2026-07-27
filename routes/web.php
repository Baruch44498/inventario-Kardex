<?php

use App\Http\Controllers\AlertaStockController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\ModuloPlaceholderController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Controllers\NotaIngresoController;
use App\Http\Controllers\NotaSalidaController;
use App\Http\Controllers\OrdenOperacionController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\RepisaController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'store'])
        ->name('login.store');
});

Route::middleware(['auth', 'usuario.activo'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::middleware('rol:ADMINISTRADOR,ALMACEN')->group(function () {
        Route::get('/productos', [ProductoController::class, 'index'])
            ->name('productos.index');

        Route::get('/productos/crear', [ProductoController::class, 'create'])
            ->name('productos.create');

        Route::post('/productos', [ProductoController::class, 'store'])
            ->name('productos.store');

        Route::get('/productos/{producto}', [ProductoController::class, 'show'])
            ->whereNumber('producto')
            ->name('productos.show');

        Route::get(
            '/productos/{producto}/editar',
            [ProductoController::class, 'edit']
        )
            ->whereNumber('producto')
            ->name('productos.edit');

        Route::put(
            '/productos/{producto}',
            [ProductoController::class, 'update']
        )
            ->whereNumber('producto')
            ->name('productos.update');

        Route::patch(
            '/productos/{producto}/estado',
            [ProductoController::class, 'toggle']
        )
            ->whereNumber('producto')
            ->name('productos.toggle');

        Route::get('/inventario', [InventarioController::class, 'index'])
            ->name('inventario.index');

        Route::get(
            '/inventario/{inventario}/editar',
            [InventarioController::class, 'edit']
        )
            ->whereNumber('inventario')
            ->name('inventario.edit');

        Route::put(
            '/inventario/{inventario}',
            [InventarioController::class, 'update']
        )
            ->whereNumber('inventario')
            ->name('inventario.update');

        Route::get('/repisas', [RepisaController::class, 'index'])
            ->name('repisas.index');

        Route::get('/repisas/crear', [RepisaController::class, 'create'])
            ->name('repisas.create');

        Route::post('/repisas', [RepisaController::class, 'store'])
            ->name('repisas.store');

        Route::get(
            '/repisas/{repisa}/editar',
            [RepisaController::class, 'edit']
        )
            ->whereNumber('repisa')
            ->name('repisas.edit');

        Route::put('/repisas/{repisa}', [RepisaController::class, 'update'])
            ->whereNumber('repisa')
            ->name('repisas.update');

        Route::patch(
            '/repisas/{repisa}/estado',
            [RepisaController::class, 'toggle']
        )
            ->whereNumber('repisa')
            ->name('repisas.toggle');

        Route::get(
            '/movimientos',
            [MovimientoInventarioController::class, 'index']
        )->name('movimientos.index');

        Route::get(
            '/movimientos/{movimiento}',
            [MovimientoInventarioController::class, 'show']
        )
            ->whereNumber('movimiento')
            ->name('movimientos.show');

        Route::get(
            '/alertas-stock',
            [AlertaStockController::class, 'index']
        )->name('alertas.index');

        Route::post(
            '/alertas-stock/evaluar',
            [AlertaStockController::class, 'evaluar']
        )->name('alertas.evaluar');

        Route::patch(
            '/alertas-stock/{alerta}/atender',
            [AlertaStockController::class, 'atender']
        )
            ->whereNumber('alerta')
            ->name('alertas.atender');


        Route::get(
            '/notas-ingreso',
            [NotaIngresoController::class, 'index']
        )->name('notas-ingreso.index');

        Route::get(
            '/notas-ingreso/crear',
            [NotaIngresoController::class, 'create']
        )->name('notas-ingreso.create');

        Route::post(
            '/notas-ingreso',
            [NotaIngresoController::class, 'store']
        )->name('notas-ingreso.store');

        Route::get(
            '/notas-ingreso/{notaIngreso}',
            [NotaIngresoController::class, 'show']
        )
            ->whereNumber('notaIngreso')
            ->name('notas-ingreso.show');

        Route::get(
            '/notas-salida',
            [NotaSalidaController::class, 'index']
        )->name('notas-salida.index');

        Route::get(
            '/notas-salida/crear',
            [NotaSalidaController::class, 'create']
        )->name('notas-salida.create');

        Route::post(
            '/notas-salida',
            [NotaSalidaController::class, 'store']
        )->name('notas-salida.store');

        Route::get(
            '/notas-salida/{notaSalida}',
            [NotaSalidaController::class, 'show']
        )
            ->whereNumber('notaSalida')
            ->name('notas-salida.show');

        Route::patch(
            '/notas-salida/{notaSalida}/anular',
            [NotaSalidaController::class, 'anular']
        )
            ->whereNumber('notaSalida')
            ->name('notas-salida.anular');

        Route::get(
            '/ordenes-operacion',
            [OrdenOperacionController::class, 'index']
        )->name('ordenes-operacion.index');

        Route::get(
            '/ordenes-operacion/crear',
            [OrdenOperacionController::class, 'create']
        )->name('ordenes-operacion.create');

        Route::post(
            '/ordenes-operacion',
            [OrdenOperacionController::class, 'store']
        )->name('ordenes-operacion.store');

        Route::get(
            '/ordenes-operacion/{ordenOperacion}',
            [OrdenOperacionController::class, 'show']
        )
            ->whereNumber('ordenOperacion')
            ->name('ordenes-operacion.show');

        Route::get(
            '/ordenes-operacion/{ordenOperacion}/editar',
            [OrdenOperacionController::class, 'edit']
        )
            ->whereNumber('ordenOperacion')
            ->name('ordenes-operacion.edit');

        Route::put(
            '/ordenes-operacion/{ordenOperacion}',
            [OrdenOperacionController::class, 'update']
        )
            ->whereNumber('ordenOperacion')
            ->name('ordenes-operacion.update');

        Route::patch(
            '/ordenes-operacion/{ordenOperacion}/iniciar',
            [OrdenOperacionController::class, 'iniciar']
        )
            ->whereNumber('ordenOperacion')
            ->name('ordenes-operacion.iniciar');

        Route::patch(
            '/ordenes-operacion/{ordenOperacion}/cerrar',
            [OrdenOperacionController::class, 'cerrar']
        )
            ->whereNumber('ordenOperacion')
            ->name('ordenes-operacion.cerrar');

        Route::patch(
            '/ordenes-operacion/{ordenOperacion}/anular',
            [OrdenOperacionController::class, 'anular']
        )
            ->whereNumber('ordenOperacion')
            ->name('ordenes-operacion.anular');
    });

    /*
     * Debe mantenerse después de las rutas funcionales.
     * Los módulos todavía no implementados continúan usando el placeholder.
     */
    Route::get(
        '/modulos/{modulo}',
        [ModuloPlaceholderController::class, 'show']
    )->name('modulos.show');

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('logout');
});
