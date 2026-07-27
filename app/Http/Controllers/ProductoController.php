<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductoController extends Controller
{
    public function index(Request $request): View
    {
        $query = DB::table('productos as p')
            ->join('unidades_medida as um', 'um.id', '=', 'p.unidad_medida_id')
            ->leftJoin('marcas as m', 'm.id', '=', 'p.marca_principal_id')
            ->leftJoin('inventarios as i', 'i.producto_id', '=', 'p.id')
            ->select([
                'p.id as id_producto',
                'p.codigo',
                'p.descripcion',
                'p.estado as activo',
                'p.updated_at as actualizado_en',
                'um.codigo as unidad_codigo',
                'um.nombre as unidad_nombre',
                'm.nombre as marca_nombre',
                DB::raw('COALESCE(SUM(i.stock_actual), 0) as stock_total'),
                DB::raw('COUNT(i.id) as cantidad_repisas'),
            ])
            ->groupBy([
                'p.id',
                'p.codigo',
                'p.descripcion',
                'p.estado',
                'p.updated_at',
                'um.codigo',
                'um.nombre',
                'm.nombre',
            ]);

        if ($request->filled('q')) {
            $busqueda = trim((string) $request->q);

            $query->where(function ($subquery) use ($busqueda): void {
                $subquery
                    ->where('p.codigo', 'like', "%{$busqueda}%")
                    ->orWhere('p.descripcion', 'like', "%{$busqueda}%")
                    ->orWhere('m.nombre', 'like', "%{$busqueda}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('p.estado', $request->estado === 'activo');
        }

        if ($request->filled('unidad')) {
            $query->where('p.unidad_medida_id', (int) $request->unidad);
        }

        if ($request->filled('marca')) {
            $query->where('p.marca_principal_id', (int) $request->marca);
        }

        $productos = $query
            ->orderBy('p.codigo')
            ->paginate(15)
            ->withQueryString();

        $unidades = DB::table('unidades_medida')
            ->select(['id as id_unidad_medida', 'codigo', 'nombre', 'estado'])
            ->where('estado', true)
            ->orderBy('codigo')
            ->get();

        $marcas = DB::table('marcas')
            ->select(['id as id_marca', 'nombre', 'estado'])
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();

        $resumen = DB::table('productos')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) as activos')
            ->selectRaw('SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END) as inactivos')
            ->first();

        return view('productos.index', compact(
            'productos',
            'unidades',
            'marcas',
            'resumen'
        ));
    }

    public function create(): View
    {
        return view('productos.create', $this->catalogos());
    }

    public function store(StoreProductoRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $productoId = DB::table('productos')->insertGetId([
            'unidad_medida_id' => $datos['id_unidad_medida'],
            'marca_principal_id' => $datos['id_marca_principal'] ?? null,
            'codigo' => $datos['codigo'],
            'descripcion' => $datos['descripcion'],
            'estado' => $datos['activo'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('productos.show', $productoId)
            ->with('success', 'Producto registrado correctamente.');
    }

    public function show(int $producto): View
    {
        $productoRegistro = DB::table('productos as p')
            ->join('unidades_medida as um', 'um.id', '=', 'p.unidad_medida_id')
            ->leftJoin('marcas as m', 'm.id', '=', 'p.marca_principal_id')
            ->where('p.id', $producto)
            ->select([
                'p.id as id_producto',
                'p.unidad_medida_id as id_unidad_medida',
                'p.marca_principal_id as id_marca_principal',
                'p.codigo',
                'p.descripcion',
                'p.estado as activo',
                'p.created_at as creado_en',
                'p.updated_at as actualizado_en',
                'um.codigo as unidad_codigo',
                'um.nombre as unidad_nombre',
                'm.nombre as marca_nombre',
            ])
            ->first();

        abort_if($productoRegistro === null, 404);

        $inventarios = DB::table('inventarios as i')
            ->join('repisas as r', 'r.id', '=', 'i.repisa_id')
            ->where('i.producto_id', $producto)
            ->select([
                'i.id as id_inventario',
                'i.stock_actual',
                'i.stock_minimo',
                'i.stock_maximo',
                'i.costo_promedio_soles',
                'i.updated_at as actualizado_en',
                'r.codigo as repisa_codigo',
                'r.descripcion as repisa_descripcion',
                DB::raw("\n                    CASE\n                        WHEN i.stock_actual <= 0 THEN 'SIN_STOCK'\n                        WHEN i.stock_actual <= i.stock_minimo THEN 'BAJO_MINIMO'\n                        WHEN i.stock_maximo IS NOT NULL\n                            AND i.stock_actual > i.stock_maximo\n                            THEN 'SOBRE_MAXIMO'\n                        ELSE 'NORMAL'\n                    END as estado_stock\n                "),
            ])
            ->orderBy('r.codigo')
            ->get();

        return view('productos.show', [
            'producto' => $productoRegistro,
            'inventarios' => $inventarios,
        ]);
    }

    public function edit(int $producto): View
    {
        $productoRegistro = DB::table('productos')
            ->where('id', $producto)
            ->select([
                'id as id_producto',
                'unidad_medida_id as id_unidad_medida',
                'marca_principal_id as id_marca_principal',
                'codigo',
                'descripcion',
                'estado as activo',
                'created_at as creado_en',
                'updated_at as actualizado_en',
            ])
            ->first();

        abort_if($productoRegistro === null, 404);

        return view('productos.edit', [
            'producto' => $productoRegistro,
            ...$this->catalogos(),
        ]);
    }

    public function update(UpdateProductoRequest $request, int $producto): RedirectResponse
    {
        $datos = $request->validated();

        $actualizados = DB::table('productos')
            ->where('id', $producto)
            ->update([
                'unidad_medida_id' => $datos['id_unidad_medida'],
                'marca_principal_id' => $datos['id_marca_principal'] ?? null,
                'codigo' => $datos['codigo'],
                'descripcion' => $datos['descripcion'],
                'estado' => $datos['activo'],
                'updated_at' => now(),
            ]);

        abort_if(
            $actualizados === 0 && ! DB::table('productos')->where('id', $producto)->exists(),
            404
        );

        return redirect()
            ->route('productos.show', $producto)
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function toggle(int $producto): RedirectResponse
    {
        $registro = DB::table('productos')
            ->where('id', $producto)
            ->first(['estado']);

        abort_if($registro === null, 404);

        $nuevoEstado = ! (bool) $registro->estado;

        DB::table('productos')
            ->where('id', $producto)
            ->update([
                'estado' => $nuevoEstado,
                'updated_at' => now(),
            ]);

        return back()->with(
            'success',
            $nuevoEstado
                ? 'Producto activado correctamente.'
                : 'Producto desactivado correctamente.'
        );
    }

    private function catalogos(): array
    {
        return [
            'unidades' => DB::table('unidades_medida')
                ->select(['id as id_unidad_medida', 'codigo', 'nombre', 'estado'])
                ->where('estado', true)
                ->orderBy('codigo')
                ->get(),
            'marcas' => DB::table('marcas')
                ->select(['id as id_marca', 'nombre', 'estado'])
                ->where('estado', true)
                ->orderBy('nombre')
                ->get(),
        ];
    }
}
