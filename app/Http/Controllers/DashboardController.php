<?php

namespace App\Http\Controllers;

use App\Models\AlertaStock;
use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\FacturaProveedor;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\NotaIngreso;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\User;
use Illuminate\Http\Request;
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
            'COMERCIAL_LOGISTICA' => 'comercial',
            'CONTABILIDAD' => 'contabilidad',
            default => 'sin_perfil',
        };

        $resumen = [];
        $movimientosRecientes = collect();
        $alertasRecientes = collect();

        if ($modo === 'administrador') {
            $resumen = [
                'usuarios_activos' => User::query()->where('estado', true)->count(),
                'clientes_activos' => Cliente::query()->where('estado', true)->count(),
                'proveedores_activos' => Proveedor::query()->where('estado', true)->count(),
                'productos_activos' => Producto::query()->where('estado', true)->count(),
                'cotizaciones_abiertas' => CotizacionCliente::query()
                    ->where('estado', 'ABIERTA')
                    ->count(),
                'facturas_pendientes' => FacturaProveedor::query()
                    ->where('estado', 'REGISTRADA')
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
                'ingresos_hoy' => NotaIngreso::query()
                    ->where('estado', 'CONFIRMADA')
                    ->whereDate('fecha_ingreso', today())
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

        if ($modo === 'comercial') {
            $resumen = [
                'clientes_activos' => Cliente::query()
                    ->where('estado', true)
                    ->count(),
                'cotizaciones_abiertas' => CotizacionCliente::query()
                    ->where('estado', 'ABIERTA')
                    ->count(),
                'proveedores_activos' => Proveedor::query()
                    ->where('estado', true)
                    ->count(),
                'compras_aprobadas' => SolicitudCompra::query()
                    ->where('estado', 'CONVERTIDA')
                    ->count(),
            ];
        }

        if ($modo === 'contabilidad') {
            $facturasPendientes = FacturaProveedor::query()
                ->where('estado', 'REGISTRADA');

            $resumen = [
                'facturas_pendientes' => (clone $facturasPendientes)->count(),
                'facturas_vencidas' => (clone $facturasPendientes)
                    ->whereDate('fecha_vencimiento', '<', today())
                    ->count(),
                'facturas_con_recepcion' => FacturaProveedor::query()
                    ->where('estado', 'REGISTRADA')
                    ->whereHas('notasIngreso', fn($nota) => $nota->where('estado', 'CONFIRMADA'))
                    ->count(),
                'total_pendiente_soles' => (clone $facturasPendientes)
                    ->get()
                    ->sum(fn(FacturaProveedor $factura) => $factura->totalEnSoles()),
            ];
        }

        return view('dashboard.index', compact(
            'perfil',
            'modo',
            'resumen',
            'movimientosRecientes',
            'alertasRecientes'
        ));
    }
}
