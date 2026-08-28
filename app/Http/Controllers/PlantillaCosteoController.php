<?php

namespace App\Http\Controllers;

use App\Models\CotizacionComponente;
use App\Models\ImportacionPlantillaCosteo;
use App\Models\PlantillaCosteo;
use App\Services\Ventas\PlantillaCosteoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlantillaCosteoController extends Controller
{
    public function __construct(
        private readonly PlantillaCosteoService $plantillas
    ) {}

    public function index(Request $request): View
    {
        $plantillas = PlantillaCosteo::query()
            ->with(['tipoOrden', 'creadoPor'])
            ->withCount('partidas')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $importacionesBorrador = ImportacionPlantillaCosteo::query()
            ->with('tipoOrden')
            ->withCount([
                'partidas as pendientes_count' => fn($query) => $query
                    ->where('omitida', false)
                    ->where('estado_vinculacion', 'PENDIENTE'),
            ])
            ->where('estado', 'BORRADOR')
            ->where('creado_por', $request->user()->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('plantillas_costeo.index', compact('plantillas', 'importacionesBorrador'));
    }

    public function guardarDesdeComponente(
        Request $request,
        CotizacionComponente $componente
    ): RedirectResponse {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:5', 'max:180'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ]);

        $plantilla = $this->plantillas->guardarDesdeComponente(
            $componente,
            $datos['nombre'],
            $datos['descripcion'] ?? null,
            $request->user()
        );

        return back()->with(
            'success',
            "Plantilla {$plantilla->nombre} guardada con {$plantilla->partidas->count()} partidas."
        );
    }

    public function aplicar(
        Request $request,
        CotizacionComponente $componente
    ): RedirectResponse {
        $datos = $request->validate([
            'plantilla_id' => [
                'required',
                'integer',
                Rule::exists('plantillas_costeo', 'id')
                    ->where('activo', true),
            ],
        ]);
        $plantilla = PlantillaCosteo::query()->findOrFail($datos['plantilla_id']);
        $cantidad = $this->plantillas->aplicar(
            $plantilla,
            $componente,
            $request->user()
        );

        return redirect()
            ->route('cotizaciones-cliente.presupuesto.show', [
                'cotizacionCliente' => $componente->cotizacion_cliente_id,
                'componente_id' => $componente->id,
            ])
            ->with(
                'success',
                "Plantilla {$plantilla->nombre} aplicada: {$cantidad} partidas cargadas."
            );
    }
}
