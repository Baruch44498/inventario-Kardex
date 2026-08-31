<?php

namespace App\Http\Controllers;

use App\Models\CotizacionPresupuesto;
use App\Models\ImportacionPlantillaCosteo;
use App\Models\ImportacionPlantillaCosteoPartida;
use App\Models\TipoOrden;
use App\Services\Ventas\ImportarPlantillaCosteoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ImportacionPlantillaCosteoController extends Controller
{
    public function create(): View
    {
        $tiposOrden = TipoOrden::query()
            ->where('estado', true)
            ->whereIn('codigo', ['OM', 'OS', 'OP'])
            ->orderBy('codigo')
            ->get();

        return view('plantillas_costeo.importar', compact('tiposOrden'));
    }

    public function store(
        Request $request,
        ImportarPlantillaCosteoService $importador
    ): RedirectResponse {
        $datos = $request->validate([
            'tipo_orden_id' => [
                'required',
                'integer',
                Rule::exists('tipos_orden', 'id')->where(
                    fn($query) => $query->where('estado', true)->whereIn('codigo', ['OM', 'OS', 'OP'])
                ),
            ],
            'nombre' => ['required', 'string', 'min:5', 'max:180'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'documento' => ['required', 'file', 'max:15360', 'mimes:xlsx,xls'],
        ], [
            'documento.required' => 'Selecciona el Excel de costos.',
            'documento.mimes' => 'Usa un archivo Excel .xlsx o .xls.',
            'documento.max' => 'El archivo no puede superar 15 MB.',
        ]);

        $archivo = $request->file('documento');
        $extension = mb_strtolower($archivo->getClientOriginalExtension());
        $ruta = $archivo->storeAs(
            'plantillas-costeo/importaciones/' . now()->format('Y/m'),
            Str::uuid()->toString() . '.' . $extension,
            'local'
        );

        try {
            $importacion = $importador->crearBorrador(
                Storage::disk('local')->path($ruta),
                [
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'ruta_archivo' => $ruta,
                    'mime_type' => $archivo->getClientMimeType(),
                ],
                $datos,
                $request->user()
            );
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($ruta);
            throw ValidationException::withMessages([
                'documento' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'No se pudo leer el Excel. Verifica que el archivo no esté dañado y use el formato de costos.',
            ]);
        }

        return redirect()
            ->route('plantillas-costeo.importaciones.show', $importacion)
            ->with('success', 'Excel leído. Revisa las coincidencias antes de crear la plantilla.');
    }

    public function show(Request $request, ImportacionPlantillaCosteo $importacion): View
    {
        $this->autorizar($request, $importacion);
        $importacion->load('tipoOrden');
        $base = $importacion->partidas();
        $resumen = [
            'total' => (clone $base)->where('omitida', false)->count(),
            'vinculadas' => (clone $base)->where('omitida', false)->where('estado_vinculacion', 'VINCULADA')->count(),
            'pendientes' => (clone $base)
                ->where('omitida', false)
                ->where(function ($query): void {
                    $query->where('estado_vinculacion', 'PENDIENTE')
                        ->orWhere(function ($query): void {
                            $query->where('tipo_costo', 'SERVICIO_TERCERO')
                                ->where(function ($query): void {
                                    $query->whereNull('ejecucion_servicio')
                                        ->orWhereNotIn('ejecucion_servicio', ['EXTERNO', 'INTERNO_HIDROIL']);
                                });
                        });
                })
                ->count(),
            'omitidas' => (clone $base)->where('omitida', true)->count(),
        ];
        $partidas = $base->with('producto.unidadMedida')
            ->paginate(50)
            ->withQueryString();

        return view('plantillas_costeo.importacion_revision', compact(
            'importacion',
            'partidas',
            'resumen'
        ));
    }

    public function updatePartida(
        Request $request,
        ImportacionPlantillaCosteoPartida $partida,
        ImportarPlantillaCosteoService $importador
    ): RedirectResponse {
        $partida->load('importacion');
        $this->autorizar($request, $partida->importacion);
        $datos = $request->validate([
            'accion' => ['required', Rule::in(['GUARDAR', 'OMITIR', 'RESTAURAR'])],
            'tipo_costo' => ['nullable', Rule::in(array_keys(CotizacionPresupuesto::TIPOS))],
            'ejecucion_servicio' => [
                'nullable',
                Rule::requiredIf($request->input('tipo_costo') === 'SERVICIO_TERCERO'),
                Rule::in(['EXTERNO', 'INTERNO_HIDROIL']),
            ],
            'producto_id' => [
                'nullable',
                'integer',
                Rule::exists('productos', 'id')->where('estado', true),
            ],
            'unidad' => ['nullable', Rule::in(array_keys(CotizacionPresupuesto::UNIDADES))],
            'grupo_costo' => ['nullable', 'string', 'max:150'],
            'descripcion' => ['required_if:accion,GUARDAR', 'nullable', 'string', 'max:300'],
            'cantidad' => ['required_if:accion,GUARDAR', 'nullable', 'numeric', 'gt:0', 'max:999999.999'],
            'costo_unitario' => ['required_if:accion,GUARDAR', 'nullable', 'numeric', 'gt:0', 'max:999999.9999'],
            'margen_porcentaje' => ['required_if:accion,GUARDAR', 'nullable', 'numeric', 'gte:0', 'max:999.9999'],
            'tipo_cambio' => ['required_if:accion,GUARDAR', 'nullable', 'numeric', 'gte:0.1', 'max:100'],
        ]);
        if ($datos['accion'] === 'GUARDAR' && empty($datos['tipo_costo'])) {
            throw ValidationException::withMessages(['tipo_costo' => 'Selecciona el tipo de costo.']);
        }

        $importador->actualizarPartida($partida, $datos);

        return back()->with('success', 'Fila del Excel actualizada.');
    }

    public function reanalizar(
        Request $request,
        ImportacionPlantillaCosteo $importacion,
        ImportarPlantillaCosteoService $importador
    ): RedirectResponse {
        $this->autorizar($request, $importacion);
        $cantidad = $importador->reanalizar($importacion);

        return back()->with(
            'success',
            $cantidad > 0
                ? "Se vincularon {$cantidad} materiales nuevos por código."
                : 'No aparecieron coincidencias nuevas. Puedes vincularlas manualmente.'
        );
    }

    public function confirmar(
        Request $request,
        ImportacionPlantillaCosteo $importacion,
        ImportarPlantillaCosteoService $importador
    ): RedirectResponse {
        $this->autorizar($request, $importacion);
        $plantilla = $importador->confirmar($importacion, $request->user());

        return redirect()
            ->route('plantillas-costeo.index')
            ->with(
                'success',
                "Plantilla {$plantilla->nombre} creada con {$plantilla->partidas->count()} partidas del Excel."
            );
    }

    private function autorizar(Request $request, ImportacionPlantillaCosteo $importacion): void
    {
        abort_unless(
            (int) $importacion->creado_por === (int) $request->user()->id
                || $request->user()->esAdministrador(),
            403
        );
    }
}
