<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateInventarioRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventarioController extends Controller
{
    public function index(Request $request): View
    {
        $estadoSql = "
            CASE
                WHEN i.stock_actual <= 0 THEN 'SIN_STOCK'
                WHEN i.stock_actual <= i.stock_minimo THEN 'BAJO_MINIMO'
                WHEN i.stock_maximo IS NOT NULL
                    AND i.stock_actual > i.stock_maximo
                    THEN 'SOBRE_MAXIMO'
                ELSE 'NORMAL'
            END
        ";

        $reservasPorProducto = $this->reservasPendientesPorProducto();
        $totalesPorProducto = $this->totalesInventarioPorProducto();

        $query = DB::table('inventarios as i')
            ->join('productos as p', 'p.id', '=', 'i.producto_id')
            ->join('unidades_medida as um', 'um.id', '=', 'p.unidad_medida_id')
            ->join('repisas as r', 'r.id', '=', 'i.repisa_id')
            ->leftJoinSub($reservasPorProducto, 'rv', fn($join) => $join
                ->on('rv.producto_id', '=', 'i.producto_id'))
            ->leftJoinSub($totalesPorProducto, 'tot', fn($join) => $join
                ->on('tot.producto_id', '=', 'i.producto_id'))
            ->select([
                'i.id as id_inventario',
                'i.stock_actual',
                'i.stock_minimo',
                'i.stock_maximo',
                'i.costo_promedio_soles',
                'i.updated_at as actualizado_en',
                'p.id as id_producto',
                'p.codigo as producto_codigo',
                'p.descripcion as producto_descripcion',
                'p.estado as producto_activo',
                'um.codigo as unidad_codigo',
                'r.id as id_repisa',
                'r.codigo as repisa_codigo',
                DB::raw("{$estadoSql} as estado_stock"),
                DB::raw('(i.stock_actual * i.costo_promedio_soles) as valor_total'),
                DB::raw('COALESCE(tot.stock_fisico, 0) as stock_fisico_total'),
                DB::raw('COALESCE(tot.stock_minimo, 0) as stock_minimo_total'),
                DB::raw('COALESCE(rv.reservado, 0) as reservado_total'),
                DB::raw('(COALESCE(tot.stock_fisico, 0) - COALESCE(rv.reservado, 0)) as disponible_total'),
                DB::raw('CASE WHEN (COALESCE(rv.reservado, 0) + COALESCE(tot.stock_minimo, 0) - COALESCE(tot.stock_fisico, 0)) > 0 THEN (COALESCE(rv.reservado, 0) + COALESCE(tot.stock_minimo, 0) - COALESCE(tot.stock_fisico, 0)) ELSE 0 END as necesidad_abastecimiento'),
            ]);

        if ($request->filled('q')) {
            $busqueda = trim((string) $request->q);

            $query->where(function ($subquery) use ($busqueda): void {
                $subquery
                    ->where('p.codigo', 'like', "%{$busqueda}%")
                    ->orWhere('p.descripcion', 'like', "%{$busqueda}%")
                    ->orWhere('r.codigo', 'like', "%{$busqueda}%");
            });
        }

        if ($request->filled('repisa')) {
            $query->where('i.repisa_id', (int) $request->repisa);
        }

        if ($request->filled('estado_stock')) {
            $query->whereRaw("({$estadoSql}) = ?", [$request->estado_stock]);
        }

        $inventarios = $query
            ->orderBy('p.codigo')
            ->orderBy('r.codigo')
            ->paginate(15)
            ->withQueryString();

        $repisaFiltro = $request->filled('repisa')
            ? DB::table('repisas')
            ->select(['id as id_repisa', 'codigo', 'descripcion', 'estado'])
            ->where('id', $request->integer('repisa'))
            ->first()
            : null;

        $resumen = DB::table('inventarios')
            ->selectRaw('COUNT(*) as ubicaciones')
            ->selectRaw('SUM(CASE WHEN stock_actual <= 0 THEN 1 ELSE 0 END) as sin_stock')
            ->selectRaw('SUM(CASE WHEN stock_actual > 0 AND stock_actual <= stock_minimo THEN 1 ELSE 0 END) as bajo_minimo')
            ->selectRaw('COALESCE(SUM(stock_actual * costo_promedio_soles), 0) as valor_total')
            ->first();

        $resumen->productos_reservados = DB::query()
            ->fromSub($this->reservasPendientesPorProducto(), 'rv')
            ->where('rv.reservado', '>', 0)
            ->count();

        $resumen->requieren_abastecimiento = DB::table('productos as p')
            ->leftJoinSub($this->reservasPendientesPorProducto(), 'rv', fn($join) => $join
                ->on('rv.producto_id', '=', 'p.id'))
            ->leftJoinSub($this->totalesInventarioPorProducto(), 'tot', fn($join) => $join
                ->on('tot.producto_id', '=', 'p.id'))
            ->where('p.estado', true)
            ->whereRaw('(COALESCE(rv.reservado, 0) + COALESCE(tot.stock_minimo, 0) - COALESCE(tot.stock_fisico, 0)) > 0.0001')
            ->count();

        $herramientasEnUso = $this->herramientasEnUso();

        return view('inventario.index', compact(
            'inventarios',
            'repisaFiltro',
            'resumen',
            'herramientasEnUso'
        ));
    }

    public function edit(int $inventario): View
    {
        $registro = DB::table('inventarios as i')
            ->join('productos as p', 'p.id', '=', 'i.producto_id')
            ->join('repisas as r', 'r.id', '=', 'i.repisa_id')
            ->join('unidades_medida as um', 'um.id', '=', 'p.unidad_medida_id')
            ->where('i.id', $inventario)
            ->select([
                'i.id as id_inventario',
                'i.producto_id as id_producto',
                'i.repisa_id as id_repisa',
                'i.stock_actual',
                'i.stock_minimo',
                'i.stock_maximo',
                'i.costo_promedio_soles',
                'i.created_at as creado_en',
                'i.updated_at as actualizado_en',
                'p.codigo as producto_codigo',
                'p.descripcion as producto_descripcion',
                'r.codigo as repisa_codigo',
                'um.codigo as unidad_codigo',
            ])
            ->first();

        abort_if($registro === null, 404);

        return view('inventario.edit', ['inventario' => $registro]);
    }

    public function update(UpdateInventarioRequest $request, int $inventario): RedirectResponse
    {
        $datos = $request->validated();

        $existe = DB::table('inventarios')->where('id', $inventario)->exists();
        abort_unless($existe, 404);

        DB::table('inventarios')
            ->where('id', $inventario)
            ->update([
                'stock_minimo' => $datos['stock_minimo'],
                'stock_maximo' => $datos['stock_maximo'] ?? null,
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('inventario.index')
            ->with('success', 'Límites de inventario actualizados correctamente.');
    }

    private function reservasPendientesPorProducto()
    {
        return DB::table('reservas_materiales_orden as rm')
            ->join('ordenes_operacion as o', 'o.id', '=', 'rm.orden_operacion_id')
            ->where('rm.estado', 'ACTIVA')
            ->whereIn('o.estado', ['ABIERTA', 'EN_PROCESO'])
            ->groupBy('rm.producto_id')
            ->selectRaw('rm.producto_id')
            ->selectRaw(
                'SUM(CASE WHEN (rm.cantidad_reservada - rm.cantidad_atendida - rm.cantidad_liberada) > 0 THEN (rm.cantidad_reservada - rm.cantidad_atendida - rm.cantidad_liberada) ELSE 0 END) as reservado'
            );
    }

    private function totalesInventarioPorProducto()
    {
        return DB::table('inventarios')
            ->groupBy('producto_id')
            ->selectRaw('producto_id')
            ->selectRaw('COALESCE(SUM(stock_actual), 0) as stock_fisico')
            ->selectRaw('COALESCE(SUM(stock_minimo), 0) as stock_minimo');
    }

    private function herramientasEnUso()
    {
        $retornos = DB::table('nota_ingreso_detalles as d')
            ->join('notas_ingreso as n', 'n.id', '=', 'd.nota_ingreso_id')
            ->where('n.estado', 'CONFIRMADA')
            ->whereNotNull('d.nota_salida_detalle_id')
            ->groupBy('d.nota_salida_detalle_id')
            ->selectRaw('d.nota_salida_detalle_id')
            ->selectRaw('SUM(d.cantidad) as retornado');

        return DB::table('nota_salida_detalles as d')
            ->join('notas_salida as n', 'n.id', '=', 'd.nota_salida_id')
            ->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->join('unidades_medida as um', 'um.id', '=', 'p.unidad_medida_id')
            ->leftJoin('ordenes_operacion as o', 'o.id', '=', 'n.orden_operacion_id')
            ->leftJoinSub($retornos, 'ret', fn($join) => $join
                ->on('ret.nota_salida_detalle_id', '=', 'd.id'))
            ->where('n.estado', 'CONFIRMADA')
            ->where('d.tratamiento', 'USO_TEMPORAL')
            ->whereRaw('(d.cantidad - COALESCE(ret.retornado, 0)) > 0.0001')
            ->select([
                'd.id as detalle_id',
                'n.id as nota_id',
                'n.codigo as nota_codigo',
                'n.fecha_salida',
                'n.entregado_a',
                'p.codigo as producto_codigo',
                'p.descripcion as producto_descripcion',
                'um.codigo as unidad_codigo',
                'o.codigo_orden',
                DB::raw('(d.cantidad - COALESCE(ret.retornado, 0)) as pendiente'),
            ])
            ->orderByDesc('n.fecha_salida')
            ->orderByDesc('d.id')
            ->limit(10)
            ->get();
    }
}
