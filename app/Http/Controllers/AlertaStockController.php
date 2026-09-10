<?php

namespace App\Http\Controllers;

use App\Models\AlertaStock;
use App\Services\Inventario\AtenderAlertaStockService;
use App\Services\Inventario\DisponibilidadMaterialService;
use App\Services\Inventario\EvaluarAlertasStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        $query = $this->consultaAlertas($filtros);
        $elegiblesFiltrados = (clone $query)
            ->whereIn('a.estado', ['ACTIVA', 'ATENDIDA'])
            ->whereNull('rar.requisicion_activa_id')
            ->distinct()
            ->count('a.id');

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

        return view('alertas.index', [
            'alertas' => $alertas,
            'resumen' => $resumen,
            'elegiblesFiltrados' => $elegiblesFiltrados,
            'puedeCrearRequerimiento' => $request->user()->puede('requerimientos.compra.crear')
                || $request->user()->esAdministrador(),
        ]);
    }

    public function prepararRequerimiento(
        Request $request,
        DisponibilidadMaterialService $disponibilidad
    ): RedirectResponse {
        abort_unless(
            $request->user()->puede('requerimientos.compra.crear')
                || $request->user()->esAdministrador(),
            403
        );

        $data = $request->validate([
            'alcance' => ['required', 'in:SELECCIONADAS,FILTRADAS'],
            'alerta_ids' => ['nullable', 'array', 'max:500'],
            'alerta_ids.*' => ['integer', 'distinct', 'exists:alertas_stock,id'],
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:ACTIVA,ATENDIDA,RESUELTA'],
            'nivel' => ['nullable', 'in:CRITICA,ADVERTENCIA'],
            'tipo' => ['nullable', 'in:SIN_STOCK,STOCK_MINIMO'],
        ]);

        if ($data['alcance'] === 'SELECCIONADAS' && empty($data['alerta_ids'])) {
            throw ValidationException::withMessages([
                'alerta_ids' => 'Selecciona al menos una alerta para preparar el requerimiento.',
            ]);
        }

        $query = $this->consultaAlertas($data)
            ->whereIn('a.estado', ['ACTIVA', 'ATENDIDA'])
            ->whereNull('rar.requisicion_activa_id');

        if ($data['alcance'] === 'SELECCIONADAS') {
            $query->whereIn('a.id', $data['alerta_ids']);
        }

        $alertas = $query
            ->get([
                'a.id',
                'a.producto_id',
                'a.nivel',
                'r.codigo as repisa_codigo',
            ]);

        if ($alertas->isEmpty()) {
            throw ValidationException::withMessages([
                'alerta_ids' => 'No se encontraron alertas activas o atendidas con la selección indicada.',
            ]);
        }

        $productoIds = $alertas
            ->pluck('producto_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $resumenes = $disponibilidad->resumenesProductos($productoIds);

        $detalles = $productoIds
            ->map(function (int $productoId) use ($alertas, $resumenes): array {
                $alertasProducto = $alertas->where('producto_id', $productoId);
                $repisas = $alertasProducto
                    ->pluck('repisa_codigo')
                    ->filter()
                    ->unique()
                    ->values();
                $sugerida = round(
                    (float) ($resumenes->get($productoId)['necesidad_abastecimiento'] ?? 0),
                    3
                );

                return [
                    'producto_id' => $productoId,
                    'cantidad_solicitada' => $sugerida > 0 ? $sugerida : 1,
                    'observacion' => 'Reposición por alerta de stock. Repisas: '
                        .($repisas->isNotEmpty() ? $repisas->implode(', ') : 'sin ubicación'),
                ];
            })
            ->all();

        $prioridad = $alertas->contains(fn (object $alerta): bool => $alerta->nivel === 'CRITICA')
            ? 'ALTA'
            : 'NORMAL';

        return redirect()
            ->route('requerimientos-compra.create')
            ->withInput([
                'fecha_solicitud' => now()->toDateString(),
                'origen' => 'REPOSICION',
                'prioridad' => $prioridad,
                'descripcion' => sprintf(
                    'Reposición preparada desde %d alerta(s), consolidada en %d producto(s).',
                    $alertas->count(),
                    $productoIds->count()
                ),
                'detalles' => $detalles,
                'alerta_ids' => $alertas->pluck('id')->all(),
            ])
            ->with('info', 'Revisa las cantidades antes de guardar el requerimiento masivo.');
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

    /** @param array<string, mixed> $filtros */
    private function consultaAlertas(array $filtros): QueryBuilder
    {
        $reposicionesActivas = DB::table('alerta_stock_requisicion as ar')
            ->join('requisiciones as rq', 'rq.id', '=', 'ar.requisicion_id')
            ->where('rq.estado', '!=', 'ANULADA')
            ->selectRaw('ar.alerta_stock_id, MAX(rq.id) as requisicion_activa_id')
            ->groupBy('ar.alerta_stock_id');

        $query = DB::table('alertas_stock as a')
            ->join('productos as p', 'p.id', '=', 'a.producto_id')
            ->join('repisas as r', 'r.id', '=', 'a.repisa_id')
            ->leftJoin('users as ua', 'ua.id', '=', 'a.atendida_por')
            ->leftJoin('users as ur', 'ur.id', '=', 'a.resuelta_por')
            ->leftJoinSub(
                $reposicionesActivas,
                'rar',
                'rar.alerta_stock_id',
                '=',
                'a.id'
            )
            ->leftJoin('requisiciones as rq_activa', 'rq_activa.id', '=', 'rar.requisicion_activa_id')
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
                'rq_activa.id as requisicion_activa_id',
                'rq_activa.codigo as requisicion_activa_codigo',
                'rq_activa.estado as requisicion_activa_estado',
            ]);

        if (! empty($filtros['q'])) {
            $busqueda = trim((string) $filtros['q']);
            $query->where(function (QueryBuilder $subquery) use ($busqueda): void {
                $subquery
                    ->where('p.codigo', 'like', "%{$busqueda}%")
                    ->orWhere('p.descripcion', 'like', "%{$busqueda}%")
                    ->orWhere('r.codigo', 'like', "%{$busqueda}%")
                    ->orWhere('a.mensaje', 'like', "%{$busqueda}%");
            });
        }

        foreach (['estado', 'nivel'] as $campo) {
            if (! empty($filtros[$campo])) {
                $query->where("a.{$campo}", $filtros[$campo]);
            }
        }

        if (! empty($filtros['tipo'])) {
            $query->where('a.tipo_alerta', $filtros['tipo']);
        }

        return $query;
    }
}
