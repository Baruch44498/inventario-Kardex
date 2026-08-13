<?php

namespace App\Http\Controllers;

use App\Models\AlertaStock;
use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\NotaSalida;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();
        $codigoRol = $usuario->role?->codigo ?? '';
        $perfil = config("hidroil_permisos.roles.{$codigoRol}", [
            'nombre' => 'Sin rol',
            'perfil' => 'Perfil no configurado',
            'descripcion' => 'Contacta al administrador.',
        ]);

        $modo = match ($codigoRol) {
            'ADMINISTRADOR' => 'administrador',
            'ALMACEN' => 'almacen',
            'COMERCIAL_LOGISTICA', 'JEFE_PLANTA' => 'ordenes',
            'CONTABILIDAD' => 'contabilidad',
            default => 'sin_perfil',
        };

        $resumen = [];
        $movimientosRecientes = collect();
        $alertasRecientes = collect();
        $ordenesRecientes = collect();

        if ($modo === 'administrador') {
            $resumen = [
                'usuarios_activos' => User::query()->where('estado', true)->count(),
                'clientes_activos' => Cliente::query()->where('estado', true)->count(),
                'proveedores_activos' => Proveedor::query()->where('estado', true)->count(),
                'productos_activos' => Producto::query()->where('estado', true)->count(),
                'cotizaciones_abiertas' => CotizacionCliente::query()
                    ->where('estado', 'ABIERTA')
                    ->count(),
                'ordenes_en_curso' => OrdenOperacion::query()
                    ->whereIn('estado', ['ABIERTA', 'EN_PROCESO'])
                    ->count(),
            ];
        }

        if ($modo === 'almacen') {
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
        }

        if ($modo === 'ordenes') {
            $resumen = [
                'abiertas' => OrdenOperacion::query()
                    ->where('estado', 'ABIERTA')
                    ->count(),
                'en_proceso' => OrdenOperacion::query()
                    ->where('estado', 'EN_PROCESO')
                    ->count(),
                'cerradas_mes' => OrdenOperacion::query()
                    ->where('estado', 'CERRADA')
                    ->whereMonth('cerrado_en', now()->month)
                    ->whereYear('cerrado_en', now()->year)
                    ->count(),
                'salidas_hoy' => NotaSalida::query()
                    ->where('estado', 'CONFIRMADA')
                    ->whereDate('fecha_salida', today())
                    ->count(),
            ];

            $ordenesRecientes = OrdenOperacion::query()
                ->with(['tipoOrden', 'cliente', 'vehiculo'])
                ->whereIn('estado', ['ABIERTA', 'EN_PROCESO'])
                ->latest('fecha_apertura')
                ->limit(8)
                ->get();
        }

        if ($modo === 'contabilidad') {
            $resumen = [
                'solicitudes_pendientes' => SolicitudCompra::query()
                    ->where('estado', 'PENDIENTE')
                    ->count(),
                'ordenes_cerradas' => OrdenOperacion::query()
                    ->where('estado', 'CERRADA')
                    ->count(),
                'salidas_confirmadas' => NotaSalida::query()
                    ->where('estado', 'CONFIRMADA')
                    ->count(),
                'documentos_hoy' => OrdenOperacion::query()
                    ->where('estado', 'CERRADA')
                    ->whereDate('cerrado_en', today())
                    ->count(),
            ];
        }

        return view('dashboard.index', compact(
            'perfil',
            'modo',
            'resumen',
            'movimientosRecientes',
            'alertasRecientes',
            'ordenesRecientes'
        ));
    }
}
