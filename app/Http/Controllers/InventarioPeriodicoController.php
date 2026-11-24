<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularInventarioPeriodicoRequest;
use App\Http\Requests\GuardarConteoInventarioPeriodicoRequest;
use App\Http\Requests\StoreInventarioPeriodicoRequest;
use App\Models\InventarioPeriodico;
use App\Models\Repisa;
use App\Services\Inventario\InventarioPeriodicoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventarioPeriodicoController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'estado' => ['nullable', 'in:ABIERTO,CERRADO,ANULADO'],
            'repisa_id' => ['nullable', 'integer', 'exists:repisas,id'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $query = InventarioPeriodico::query()
            ->with(['repisa', 'repisas', 'abiertoPor', 'cerradoPor'])
            ->withCount([
                'detalles as lineas_contadas' => fn($detalle) =>
                $detalle->whereNotNull('stock_contado'),
            ]);

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }
        if (! empty($filtros['repisa_id'])) {
            $query->whereHas('repisas', fn ($repisas) => $repisas->where('repisas.id', (int) $filtros['repisa_id']));
        }
        if (! empty($filtros['desde'])) {
            $query->whereDate('fecha_corte', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $query->whereDate('fecha_corte', '<=', $filtros['hasta']);
        }

        $inventariosPeriodicos = $query
            ->orderByDesc('fecha_corte')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $resumen = InventarioPeriodico::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN estado = 'ABIERTO' THEN 1 ELSE 0 END) as abiertos")
            ->selectRaw("SUM(CASE WHEN estado = 'CERRADO' THEN 1 ELSE 0 END) as cerrados")
            ->selectRaw(
                "SUM(CASE WHEN estado = 'CERRADO' THEN lineas_con_diferencia ELSE 0 END) as diferencias"
            )
            ->first();

        $repisaFiltro = ! empty($filtros['repisa_id'])
            ? Repisa::query()->find($filtros['repisa_id'])
            : null;

        return view('inventarios_periodicos.index', compact(
            'inventariosPeriodicos',
            'resumen',
            'repisaFiltro'
        ));
    }

    public function create(Request $request): View
    {
        $repisaSeleccionada = old('repisa_id')
            ? Repisa::query()->find(old('repisa_id'))
            : null;

        $filtros = $request->validate(['prefijo' => ['nullable', 'string', 'max:40']]);
        $prefijo = trim($filtros['prefijo'] ?? '');
        $repisasGrupo = collect();
        $totalCoincidencias = 0;
        if ($prefijo !== '') {
            $coincidencias = Repisa::query()->where('estado', true)->orderBy('codigo')->get()
                ->filter(fn (Repisa $repisa) => str_starts_with(mb_strtoupper($repisa->codigo), mb_strtoupper($prefijo)));
            $totalCoincidencias = $coincidencias->count();
            $repisasGrupo = $coincidencias->take(200)->values();
        }

        return view('inventarios_periodicos.create', compact('repisaSeleccionada', 'repisasGrupo', 'prefijo', 'totalCoincidencias'));
    }

    public function store(
        StoreInventarioPeriodicoRequest $request,
        InventarioPeriodicoService $service
    ): RedirectResponse {
        $periodico = $service->abrir($request->validated(), $request->user());

        return redirect()
            ->route('inventarios-periodicos.show', $periodico)
            ->with('success', "Inventario {$periodico->codigo} abierto. Ya puedes registrar el conteo físico.");
    }

    public function show(InventarioPeriodico $inventarioPeriodico): View
    {
        $inventarioPeriodico->load([
            'repisa',
            'repisas' => fn ($query) => $query->orderBy('codigo'),
            'abiertoPor',
            'cerradoPor',
            'anuladoPor',
            'detalles' => fn($query) => $query
                ->with(['producto.unidadMedida', 'repisa', 'contadoPor'])
                ->join('productos as productos_orden', 'productos_orden.id', '=', 'inventario_periodico_detalles.producto_id')
                ->join('repisas as repisas_orden', 'repisas_orden.id', '=', 'inventario_periodico_detalles.repisa_id')
                ->orderBy('repisas_orden.codigo')
                ->orderBy('productos_orden.codigo')
                ->select('inventario_periodico_detalles.*'),
        ]);

        $lineasContadas = $inventarioPeriodico->detalles
            ->whereNotNull('stock_contado')
            ->count();

        $repisasConDetalle = $inventarioPeriodico->detalles->pluck('repisa_id')->unique();
        $repisasVacias = $inventarioPeriodico->repisas
            ->reject(fn (Repisa $repisa) => $repisasConDetalle->contains($repisa->id));

        return view('inventarios_periodicos.show', compact(
            'inventarioPeriodico',
            'lineasContadas',
            'repisasVacias'
        ));
    }

    public function guardarConteo(
        GuardarConteoInventarioPeriodicoRequest $request,
        InventarioPeriodico $inventarioPeriodico,
        InventarioPeriodicoService $service
    ): RedirectResponse {
        $service->guardarConteo(
            $inventarioPeriodico,
            $request->validated('detalles'),
            $request->user()
        );

        return redirect()
            ->route('inventarios-periodicos.show', $inventarioPeriodico)
            ->with('success', 'Avance del conteo guardado correctamente.');
    }

    public function cerrar(
        Request $request,
        InventarioPeriodico $inventarioPeriodico,
        InventarioPeriodicoService $service
    ): RedirectResponse {
        $service->cerrar($inventarioPeriodico, $request->user());

        return redirect()
            ->route('inventarios-periodicos.show', $inventarioPeriodico)
            ->with('success', 'Inventario periódico cerrado y diferencias aplicadas al Kardex.');
    }

    public function confirmarVacia(
        Request $request,
        InventarioPeriodico $inventarioPeriodico,
        Repisa $repisa,
        InventarioPeriodicoService $service
    ): RedirectResponse {
        $service->confirmarRepisaVacia($inventarioPeriodico, $repisa, $request->user());

        return redirect()->route('inventarios-periodicos.show', $inventarioPeriodico)
            ->with('success', "Repisa {$repisa->codigo} confirmada sin productos.");
    }

    public function registrarHallazgo(
        Request $request,
        InventarioPeriodico $inventarioPeriodico,
        Repisa $repisa,
        InventarioPeriodicoService $service
    ): RedirectResponse {
        $datos = $request->validate([
            'descripcion' => ['required', 'string', 'max:300'],
            'cantidad' => ['required', 'numeric', 'gt:0', 'max:99999999999.999'],
        ]);
        $service->registrarHallazgo($inventarioPeriodico, $repisa, $datos, $request->user());

        return redirect()->route('inventarios-periodicos.show', $inventarioPeriodico)
            ->with('success', "Hallazgo registrado en {$repisa->codigo}; requiere conciliación separada.");
    }

    public function corregirRepisa(
        InventarioPeriodico $inventarioPeriodico,
        Repisa $repisa,
        InventarioPeriodicoService $service
    ): RedirectResponse {
        $service->corregirRepisa($inventarioPeriodico, $repisa);

        return redirect()->route('inventarios-periodicos.show', $inventarioPeriodico)
            ->with('success', "Puedes volver a revisar la repisa {$repisa->codigo}.");
    }

    public function anular(
        AnularInventarioPeriodicoRequest $request,
        InventarioPeriodico $inventarioPeriodico,
        InventarioPeriodicoService $service
    ): RedirectResponse {
        $service->anular(
            $inventarioPeriodico,
            $request->validated('motivo_anulacion'),
            $request->user()
        );

        return redirect()
            ->route('inventarios-periodicos.show', $inventarioPeriodico)
            ->with('success', 'Conteo anulado sin modificar las existencias.');
    }
}
