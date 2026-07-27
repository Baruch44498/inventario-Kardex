<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRepisaRequest;
use App\Http\Requests\UpdateRepisaRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RepisaController extends Controller
{
    public function index(Request $request): View
    {
        $query = DB::table('repisas as r')
            ->leftJoin('inventarios as i', 'i.repisa_id', '=', 'r.id')
            ->select([
                'r.id as id_repisa',
                'r.codigo',
                'r.descripcion',
                'r.estado',
                'r.updated_at as actualizado_en',
                DB::raw('COUNT(i.id) as productos_ubicados'),
                DB::raw('COALESCE(SUM(i.stock_actual), 0) as stock_total'),
            ])
            ->groupBy([
                'r.id',
                'r.codigo',
                'r.descripcion',
                'r.estado',
                'r.updated_at',
            ]);

        if ($request->filled('q')) {
            $busqueda = trim((string) $request->q);

            $query->where(function ($subquery) use ($busqueda): void {
                $subquery
                    ->where('r.codigo', 'like', "%{$busqueda}%")
                    ->orWhere('r.descripcion', 'like', "%{$busqueda}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('r.estado', $request->estado === 'activo');
        }

        $repisas = $query
            ->orderBy('r.codigo')
            ->paginate(15)
            ->withQueryString();

        $resumen = DB::table('repisas as r')
            ->leftJoin('inventarios as i', 'i.repisa_id', '=', 'r.id')
            ->selectRaw('COUNT(DISTINCT r.id) as total')
            ->selectRaw('COUNT(DISTINCT CASE WHEN r.estado = 1 THEN r.id END) as activas')
            ->selectRaw('COUNT(DISTINCT CASE WHEN r.estado = 0 THEN r.id END) as inactivas')
            ->selectRaw('COUNT(DISTINCT CASE WHEN i.id IS NOT NULL THEN r.id END) as con_productos')
            ->first();

        return view('repisas.index', compact('repisas', 'resumen'));
    }

    public function create(): View
    {
        return view('repisas.create');
    }

    public function store(StoreRepisaRequest $request): RedirectResponse
    {
        DB::table('repisas')->insert([
            ...$request->validated(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('repisas.index')
            ->with('success', 'Repisa registrada correctamente.');
    }

    public function edit(int $repisa): View
    {
        $registro = DB::table('repisas')
            ->where('id', $repisa)
            ->select([
                'id as id_repisa',
                'codigo',
                'descripcion',
                'estado',
                'created_at as creado_en',
                'updated_at as actualizado_en',
            ])
            ->first();

        abort_if($registro === null, 404);

        return view('repisas.edit', ['repisa' => $registro]);
    }

    public function update(UpdateRepisaRequest $request, int $repisa): RedirectResponse
    {
        $existe = DB::table('repisas')->where('id', $repisa)->exists();
        abort_unless($existe, 404);

        DB::table('repisas')
            ->where('id', $repisa)
            ->update([
                ...$request->validated(),
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('repisas.index')
            ->with('success', 'Repisa actualizada correctamente.');
    }

    public function toggle(int $repisa): RedirectResponse
    {
        $registro = DB::table('repisas')
            ->where('id', $repisa)
            ->first(['estado']);

        abort_if($registro === null, 404);

        $nuevoEstado = ! (bool) $registro->estado;

        DB::table('repisas')
            ->where('id', $repisa)
            ->update([
                'estado' => $nuevoEstado,
                'updated_at' => now(),
            ]);

        return back()->with(
            'success',
            $nuevoEstado
                ? 'Repisa activada correctamente.'
                : 'Repisa desactivada correctamente.'
        );
    }
}
