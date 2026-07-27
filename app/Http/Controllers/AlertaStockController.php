<?php

namespace App\Http\Controllers;

use App\Models\AlertaStock;
use App\Services\Inventario\AtenderAlertaStockService;
use App\Services\Inventario\EvaluarAlertasStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AlertaStockController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:ACTIVA,ATENDIDA,RESUELTA'],
            'nivel' => ['nullable', 'in:CRITICA,ADVERTENCIA'],
            'tipo' => ['nullable', 'in:SIN_STOCK,STOCK_MINIMO'],
        ]);

        $query = DB::table('alertas_stock as a')
            ->join('productos as p', 'p.id', '=', 'a.producto_id')
            ->join('repisas as r', 'r.id', '=', 'a.repisa_id')
            ->leftJoin('users as ua', 'ua.id', '=', 'a.atendida_por')
            ->leftJoin('users as ur', 'ur.id', '=', 'a.resuelta_por')
            ->select([
                'a.id',
                'a.inventario_id',
                'a.tipo_alerta',
                'a.nivel',
                'a.stock_actual',
                'a.stock_minimo',
                'a.mensaje',
                'a.estado',
                'a.detectada_en',
                'a.atendida_en',
                'a.resuelta_en',
                'a.observacion_resolucion',
                'p.id as producto_id',
                'p.codigo as producto_codigo',
                'p.descripcion as producto_descripcion',
                'r.id as repisa_id',
                'r.codigo as repisa_codigo',
                'ua.username as atendida_por_nombre',
                'ur.username as resuelta_por_nombre',
            ]);

        if (! empty($filtros['q'])) {
            $busqueda = trim($filtros['q']);

            $query->where(function ($subquery) use ($busqueda): void {
                $subquery
                    ->where('p.codigo', 'like', "%{$busqueda}%")
                    ->orWhere('p.descripcion', 'like', "%{$busqueda}%")
                    ->orWhere('r.codigo', 'like', "%{$busqueda}%")
                    ->orWhere('a.mensaje', 'like', "%{$busqueda}%");
            });
        }

        if (! empty($filtros['estado'])) {
            $query->where('a.estado', $filtros['estado']);
        }

        if (! empty($filtros['nivel'])) {
            $query->where('a.nivel', $filtros['nivel']);
        }

        if (! empty($filtros['tipo'])) {
            $query->where('a.tipo_alerta', $filtros['tipo']);
        }

        $alertas = $query
            ->orderByRaw(
                "CASE a.estado
                    WHEN 'ACTIVA' THEN 1
                    WHEN 'ATENDIDA' THEN 2
                    ELSE 3
                END"
            )
            ->orderByRaw(
                "CASE a.nivel
                    WHEN 'CRITICA' THEN 1
                    ELSE 2
                END"
            )
            ->orderByDesc('a.detectada_en')
            ->paginate(20)
            ->withQueryString();

        $resumen = DB::table('alertas_stock')
            ->selectRaw(
                "SUM(CASE WHEN estado = 'ACTIVA' THEN 1 ELSE 0 END) as activas"
            )
            ->selectRaw(
                "SUM(CASE WHEN estado = 'ATENDIDA' THEN 1 ELSE 0 END) as atendidas"
            )
            ->selectRaw(
                "SUM(CASE
                    WHEN nivel = 'CRITICA'
                    AND estado IN ('ACTIVA', 'ATENDIDA')
                    THEN 1 ELSE 0 END
                ) as criticas"
            )
            ->selectRaw(
                "SUM(CASE WHEN estado = 'RESUELTA' THEN 1 ELSE 0 END) as resueltas"
            )
            ->first();

        return view('alertas.index', compact('alertas', 'resumen'));
    }

    public function evaluar(
        Request $request,
        EvaluarAlertasStockService $service
    ): RedirectResponse {
        $procesados = $service->evaluarTodos($request->user());

        return back()->with(
            'success',
            "Evaluación completada: {$procesados} ubicaciones revisadas."
        );
    }

    public function atender(
        Request $request,
        int $alerta,
        AtenderAlertaStockService $service
    ): RedirectResponse {
        $registro = AlertaStock::query()->findOrFail($alerta);

        $service->atender($registro, $request->user());

        return back()->with(
            'success',
            'Alerta marcada como atendida correctamente.'
        );
    }
}
