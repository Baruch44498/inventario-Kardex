<?php

namespace App\Http\Controllers;

use App\Models\AlertaStock;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $resumen = [
            'productos_activos' => Producto::query()
                ->where('estado', true)
                ->count(),

            'inventarios_bajo_minimo' => Inventario::query()
                ->whereColumn('stock_actual', '<=', 'stock_minimo')
                ->count(),

            'sin_stock' => Inventario::query()
                ->where('stock_actual', '<=', 0)
                ->count(),

            'ordenes_en_curso' => OrdenOperacion::query()
                ->whereIn('estado', ['ABIERTA', 'EN_PROCESO'])
                ->count(),

            'alertas_abiertas' => AlertaStock::query()
                ->whereIn('estado', ['ACTIVA', 'ATENDIDA'])
                ->count(),

            'movimientos_hoy' => MovimientoInventario::query()
                ->whereDate('fecha_movimiento', today())
                ->count(),
        ];

        $movimientosRecientes = MovimientoInventario::query()
            ->with(['producto', 'repisa', 'registrador'])
            ->latest('fecha_movimiento')
            ->limit(6)
            ->get();

        $alertasRecientes = AlertaStock::query()
            ->with(['producto', 'repisa'])
            ->whereIn('estado', ['ACTIVA', 'ATENDIDA'])
            ->latest('detectada_en')
            ->limit(5)
            ->get();

        return view('dashboard.index', compact(
            'resumen',
            'movimientosRecientes',
            'alertasRecientes'
        ));
    }
}
