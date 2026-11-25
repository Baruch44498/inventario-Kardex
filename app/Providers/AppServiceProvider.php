<?php

namespace App\Providers;

use App\Models\CobroCotizacionCliente;
use App\Models\CostoDirectoOrden;
use App\Models\CotizacionCliente;
use App\Models\FacturaProveedor;
use App\Models\InventarioPeriodico;
use App\Models\MovimientoInventario;
use App\Models\NotaIngreso;
use App\Models\NotaSalida;
use App\Models\OrdenCompra;
use App\Models\OrdenOperacion;
use App\Models\PagoFacturaProveedor;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\Requisicion;
use App\Models\User;
use App\Observers\AuditoriaObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            User::class, Producto::class, Proforma::class, CotizacionCliente::class,
            OrdenOperacion::class, Requisicion::class, OrdenCompra::class,
            NotaIngreso::class, NotaSalida::class, InventarioPeriodico::class,
            MovimientoInventario::class, CostoDirectoOrden::class,
            FacturaProveedor::class, PagoFacturaProveedor::class,
            CobroCotizacionCliente::class,
        ] as $modelo) {
            $modelo::observe(AuditoriaObserver::class);
        }
    }
}
