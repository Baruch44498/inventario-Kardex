<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularPresupuestoCotizacionRequest;
use App\Http\Requests\GuardarMaterialesEtapaCotizacionRequest;
use App\Http\Requests\GuardarPresupuestoCotizacionRequest;
use App\Models\CotizacionCliente;
use App\Models\CotizacionPresupuesto;
use App\Models\PlantillaCosteo;
use App\Services\Ventas\PresupuestoCotizacionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CotizacionPresupuestoController extends Controller
{
    public function __construct(
        private PresupuestoCotizacionService $presupuestos
    ) {}

    public function show(
        Request $request,
        CotizacionCliente $cotizacionCliente
    ): View|RedirectResponse {
        $cotizacionCliente->load([
            'cliente',
            'tipoOrden',
            'todasLasAreas',
            'componentes.tipoOrden',
            'presupuestos' => fn($query) => $query
                ->with([
                    'producto.unidadMedida',
                    'area',
                    'componente.tipoOrden',
                    'registradoPor',
                    'actualizadoPor',
                    'anuladoPor',
                ])
                ->orderByRaw("CASE WHEN estado = 'VIGENTE' THEN 0 ELSE 1 END")
                ->orderBy('tipo_costo')
                ->orderBy('id'),
        ]);

        $componenteSolicitado = $request->integer('componente_id');
        $componenteInicial = $cotizacionCliente->componentes->first(
            fn($componente): bool => $componente->id === $componenteSolicitado
        ) ?: $cotizacionCliente->componentes->first();
        $partidasComponente = $componenteInicial
            ? $cotizacionCliente->presupuestos
            ->where('componente_id', $componenteInicial->id)
            ->where('estado', 'VIGENTE')
            : collect();
        $componentePlantilla = $cotizacionCliente->componentes->first();
        $partidasPlantilla = $cotizacionCliente->presupuestos->where('estado', 'VIGENTE');
        $plantillasCompatibles = $componentePlantilla
            ? PlantillaCosteo::query()
            ->where('activo', true)
            ->where('tipo_orden_id', $cotizacionCliente->tipo_orden_id ?: $componentePlantilla->tipo_orden_id)
            ->withCount(['partidas', 'areas'])
            ->orderBy('nombre')
            ->get()
            : collect();
        $partidasVigentes = $cotizacionCliente->presupuestos
            ->where('estado', 'VIGENTE');
        $areasPresupuesto = $cotizacionCliente->todasLasAreas
            ->where('estado', 'VIGENTE')
            ->map(function ($area) use ($partidasVigentes): array {
                $lineas = $partidasVigentes
                    ->where('cotizacion_area_id', $area->id)
                    ->values();

                return [
                    'area' => $area,
                    'lineas' => $lineas,
                    'materiales' => $lineas->where('tipo_costo', 'MATERIAL')->count(),
                    'servicios' => $lineas->where('tipo_costo', 'SERVICIO_TERCERO')->count(),
                    'otros' => $lineas->whereNotIn('tipo_costo', ['MATERIAL', 'SERVICIO_TERCERO'])->count(),
                    'costo_soles' => round((float) $lineas->sum('costo_total_soles'), 4),
                    'venta_soles' => round((float) $lineas->sum('precio_venta_total_soles'), 4),
                ];
            })
            ->values();
        $areaSeleccionada = $cotizacionCliente->todasLasAreas
            ->where('estado', 'VIGENTE')
            ->firstWhere('id', $request->integer('area_id'));
        $paso = strtolower((string) $request->query('paso', 'materiales'));
        $pasosPermitidos = ['materiales', 'costos', 'revision'];

        if (! in_array($paso, $pasosPermitidos, true)) {
            $paso = 'materiales';
        }

        if (! $cotizacionCliente->esEditable()) {
            $paso = 'revision';
        }

        $parametrosPaso = [
            'cotizacionCliente' => $cotizacionCliente,
            'componente_id' => $componenteInicial?->id,
        ];
        $tienePartidas = $partidasVigentes->isNotEmpty();
        if ($paso === 'revision' && $cotizacionCliente->esEditable() && ! $tienePartidas) {
            return redirect()
                ->route('cotizaciones-cliente.presupuesto.show', [
                    ...$parametrosPaso,
                    'paso' => 'materiales',
                ])
                ->with('warning', 'Guarda al menos un material u otro costo antes de revisar la hoja.');
        }

        $tieneMateriales = $partidasVigentes->contains('tipo_costo', 'MATERIAL');
        $tieneOtrosCostos = $partidasVigentes->contains(
            fn($partida): bool => $partida->tipo_costo !== 'MATERIAL'
        );
        $pasosPresupuesto = collect([
            ['key' => 'materiales', 'number' => 1, 'name' => 'Áreas y materiales', 'description' => 'Carga por etapas y plantillas'],
            ['key' => 'costos', 'number' => 2, 'name' => 'Otros costos', 'description' => 'Personal, servicios y adicionales'],
            ['key' => 'revision', 'number' => 3, 'name' => 'Revisión final', 'description' => 'Totales, detalle y sincronización'],
        ])->map(fn(array $item): array => [
            ...$item,
            'state' => match ($item['key']) {
                'materiales' => $tieneMateriales ? 'completed' : 'pending',
                'costos' => $tieneOtrosCostos ? 'completed' : 'pending',
                default => 'pending',
            },
            'available' => $item['key'] !== 'revision' || $tienePartidas || ! $cotizacionCliente->esEditable(),
            'description' => $item['key'] === 'revision' && ! $tienePartidas && $cotizacionCliente->esEditable()
                ? 'Guarda al menos una partida'
                : $item['description'],
            'href' => route('cotizaciones-cliente.presupuesto.show', [
                ...$parametrosPaso,
                'paso' => $item['key'],
            ]),
        ])->all();

        $gruposPartidas = $cotizacionCliente->presupuestos
            ->groupBy(function (CotizacionPresupuesto $partida): string {
                if ($partida->cotizacion_area_id) {
                    return 'area:' . $partida->cotizacion_area_id;
                }

                $grupo = trim((string) $partida->grupo_costo);

                return $grupo !== '' ? 'grupo:' . sha1(mb_strtoupper($grupo)) : 'sin-area';
            })
            ->map(function ($lineas, string $clave) use ($cotizacionCliente): array {
                $area = $lineas->first()->area;
                $vigentes = $lineas->where('estado', 'VIGENTE');

                return [
                    'clave' => $clave,
                    'nombre' => $area
                        ? $area->rutaVisible($cotizacionCliente->todasLasAreas)
                        : (trim((string) $lineas->first()->grupo_costo) ?: 'Sin área asignada'),
                    'lineas' => $lineas->values(),
                    'vigentes' => $vigentes->count(),
                    'costo_soles' => round((float) $vigentes->sum('costo_total_soles'), 4),
                    'venta_soles' => round((float) $vigentes->sum('precio_venta_total_soles'), 4),
                    'utilidad_soles' => round((float) $vigentes->sum('utilidad_estimada_soles'), 4),
                ];
            })
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $grupoSeleccionado = $gruposPartidas->firstWhere('clave', $request->query('grupo_partidas'))
            ?? $gruposPartidas->first();
        $lineasSeleccionadas = $grupoSeleccionado['lineas'] ?? collect();
        $totalPartidas = $lineasSeleccionadas->count();
        $paginaPartidas = min(
            max(1, $request->integer('partidas_page', 1)),
            max(1, (int) ceil($totalPartidas / 15))
        );
        $partidasPaginadas = (new LengthAwarePaginator(
            $lineasSeleccionadas->forPage($paginaPartidas, 15)->values(),
            $totalPartidas,
            15,
            $paginaPartidas,
            ['path' => $request->url(), 'pageName' => 'partidas_page']
        ))->appends([
            ...$request->except('partidas_page'),
            'grupo_partidas' => $grupoSeleccionado['clave'] ?? null,
        ])->fragment('detalle-area-presupuesto');

        return view('cotizaciones_cliente.presupuesto', [
            'cotizacion' => $cotizacionCliente,
            'partidas' => $partidasPaginadas,
            'gruposPartidas' => $gruposPartidas,
            'grupoSeleccionado' => $grupoSeleccionado,
            'resumen' => $this->presupuestos->resumen($cotizacionCliente->presupuestos),
            'componenteInicial' => $componenteInicial,
            'partidasComponente' => $partidasComponente,
            'plantillasCompatibles' => $plantillasCompatibles,
            'componentePlantilla' => $componentePlantilla,
            'partidasPlantilla' => $partidasPlantilla,
            'areasPresupuesto' => $areasPresupuesto,
            'paso' => $paso,
            'pasoActual' => array_search($paso, $pasosPermitidos, true) + 1,
            'pasosPresupuesto' => $pasosPresupuesto,
            'tienePartidas' => $tienePartidas,
            'partida' => new CotizacionPresupuesto([
                'componente_id' => $componenteInicial?->id,
                'cotizacion_area_id' => $areaSeleccionada?->id,
                'tipo_costo' => in_array($request->query('tipo_costo'), array_keys(CotizacionPresupuesto::TIPOS), true)
                    ? $request->query('tipo_costo')
                    : null,
                'grupo_costo' => trim((string) $request->query('area')) ?: null,
                'cantidad' => 1,
                'unidad' => 'GLOBAL',
                'moneda' => $cotizacionCliente->moneda ?: 'PEN',
                'tipo_cambio' => (float) (
                    $componenteInicial?->tipo_cambio_comparacion
                    ?: $cotizacionCliente->tipo_cambio
                ) ?: null,
                'carga_social_porcentaje' => 0,
                'margen_porcentaje' => (float) $cotizacionCliente->margen_cliente_porcentaje,
                'igv_modo' => 'NO_APLICA',
                'igv_porcentaje' => 0,
                'igv_venta_porcentaje' => CotizacionPresupuesto::IGV_PORCENTAJE,
            ]),
        ]);
    }

    public function store(
        GuardarPresupuestoCotizacionRequest $request,
        CotizacionCliente $cotizacionCliente
    ): RedirectResponse {
        $presupuesto = $this->presupuestos->registrar(
            $cotizacionCliente,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('cotizaciones-cliente.presupuesto.show', [
                'cotizacionCliente' => $cotizacionCliente,
                'componente_id' => $presupuesto->componente_id,
                'paso' => 'costos',
            ])
            ->with('success', 'Partida agregada al presupuesto interno. Los importes se recalcularon en PEN y USD.');
    }

    public function storeMateriales(
        GuardarMaterialesEtapaCotizacionRequest $request,
        CotizacionCliente $cotizacionCliente
    ): RedirectResponse {
        $materiales = $this->presupuestos->registrarMaterialesEtapa(
            $cotizacionCliente,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('cotizaciones-cliente.presupuesto.show', [
                'cotizacionCliente' => $cotizacionCliente,
                'componente_id' => $request->integer('componente_id'),
            ])
            ->with(
                'success',
                $materiales->count() . ' materiales fueron agregados juntos a la etapa.'
            );
    }

    public function edit(CotizacionPresupuesto $presupuesto): View|RedirectResponse
    {
        $presupuesto->load([
            'cotizacionCliente.cliente',
            'cotizacionCliente.componentes.tipoOrden',
            'cotizacionCliente.todasLasAreas',
            'producto.unidadMedida',
            'area',
        ]);

        if (! $presupuesto->estaVigente() || ! $presupuesto->cotizacionCliente->esEditable()) {
            return redirect()
                ->route(
                    'cotizaciones-cliente.presupuesto.show',
                    $presupuesto->cotizacionCliente
                )
                ->with('error', 'Esta partida ya no puede editarse.');
        }

        return view('cotizaciones_cliente.presupuesto_edit', [
            'cotizacion' => $presupuesto->cotizacionCliente,
            'partida' => $presupuesto,
        ]);
    }

    public function update(
        GuardarPresupuestoCotizacionRequest $request,
        CotizacionPresupuesto $presupuesto
    ): RedirectResponse {
        $presupuesto = $this->presupuestos->actualizar(
            $presupuesto,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route(
                'cotizaciones-cliente.presupuesto.show',
                [
                    'cotizacionCliente' => $presupuesto->cotizacion_cliente_id,
                    'componente_id' => $presupuesto->componente_id,
                    'paso' => 'revision',
                    'partidas_page' => $request->integer('partidas_page') ?: null,
                    'grupo_partidas' => $request->query('grupo_partidas'),
                ]
            )
            ->with('success', 'Partida actualizada y recalculada.');
    }

    public function anular(
        AnularPresupuestoCotizacionRequest $request,
        CotizacionPresupuesto $presupuesto
    ): RedirectResponse {
        $presupuesto = $this->presupuestos->anular(
            $presupuesto,
            $request->validated('motivo_anulacion'),
            $request->user()
        );

        return redirect()
            ->route(
                'cotizaciones-cliente.presupuesto.show',
                [
                    'cotizacionCliente' => $presupuesto->cotizacion_cliente_id,
                    'componente_id' => $presupuesto->componente_id,
                    'paso' => 'revision',
                    'partidas_page' => $request->integer('partidas_page') ?: null,
                    'grupo_partidas' => $request->query('grupo_partidas'),
                ]
            )
            ->with('success', 'Partida anulada. Se conserva en el historial y ya no suma al presupuesto.');
    }
}
