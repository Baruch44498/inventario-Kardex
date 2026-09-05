<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularPresupuestoCotizacionRequest;
use App\Http\Requests\GuardarMaterialesEtapaCotizacionRequest;
use App\Http\Requests\GuardarPresupuestoCotizacionRequest;
use App\Models\CotizacionCliente;
use App\Models\CotizacionPresupuesto;
use App\Models\PlantillaCosteo;
use App\Services\Ventas\PresupuestoCotizacionService;
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
    ): View {
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
        $plantillasCompatibles = $componenteInicial
            ? PlantillaCosteo::query()
            ->where('activo', true)
            ->where('tipo_orden_id', $componenteInicial->tipo_orden_id)
            ->withCount('partidas')
            ->orderBy('nombre')
            ->get()
            : collect();
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
        $pasosPresupuesto = collect([
            ['key' => 'materiales', 'number' => 1, 'name' => 'Áreas y materiales', 'description' => 'Carga por etapas y plantillas'],
            ['key' => 'costos', 'number' => 2, 'name' => 'Otros costos', 'description' => 'Personal, servicios y adicionales'],
            ['key' => 'revision', 'number' => 3, 'name' => 'Revisión final', 'description' => 'Totales, detalle y sincronización'],
        ])->map(fn(array $item): array => [
            ...$item,
            'href' => route('cotizaciones-cliente.presupuesto.show', [
                ...$parametrosPaso,
                'paso' => $item['key'],
            ]),
        ])->all();

        return view('cotizaciones_cliente.presupuesto', [
            'cotizacion' => $cotizacionCliente,
            'partidas' => $cotizacionCliente->presupuestos,
            'resumen' => $this->presupuestos->resumen($cotizacionCliente->presupuestos),
            'componenteInicial' => $componenteInicial,
            'partidasComponente' => $partidasComponente,
            'plantillasCompatibles' => $plantillasCompatibles,
            'paso' => $paso,
            'pasoActual' => array_search($paso, $pasosPermitidos, true) + 1,
            'pasosPresupuesto' => $pasosPresupuesto,
            'partida' => new CotizacionPresupuesto([
                'componente_id' => $componenteInicial?->id,
                'cantidad' => 1,
                'unidad' => 'GLOBAL',
                'moneda' => $cotizacionCliente->moneda ?: 'PEN',
                'tipo_cambio' => (float) (
                    $componenteInicial?->tipo_cambio_comparacion
                    ?: $cotizacionCliente->tipo_cambio
                ) ?: null,
                'carga_social_porcentaje' => 0,
                'margen_porcentaje' => 0,
                'igv_modo' => 'NO_APLICA',
                'igv_porcentaje' => 0,
                'igv_venta_porcentaje' => 18,
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
                ]
            )
            ->with('success', 'Partida anulada. Se conserva en el historial y ya no suma al presupuesto.');
    }
}
