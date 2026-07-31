<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularOrdenOperacionRequest;
use App\Http\Requests\StoreOrdenOperacionRequest;
use App\Http\Requests\UpdateOrdenOperacionRequest;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\OrdenOperacion;
use App\Models\TipoOrden;
use App\Models\Vehiculo;
use App\Models\User;
use App\Support\PermisoSistema as P;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrdenOperacionController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', 'integer', 'exists:tipos_orden,id'],
            'estado' => ['nullable', 'in:ABIERTA,EN_PROCESO,CERRADA,ANULADA'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $query = OrdenOperacion::query()
            ->with(['tipoOrden', 'cliente', 'vehiculo', 'creador'])
            ->withCount(['requisiciones', 'notasSalida']);

        if (! empty($filtros['q'])) {
            $busqueda = trim($filtros['q']);

            $query->where(function ($subquery) use ($busqueda): void {
                $subquery
                    ->where('codigo_orden', 'like', "%{$busqueda}%")
                    ->orWhere('descripcion', 'like', "%{$busqueda}%")
                    ->orWhereHas('cliente', function ($cliente) use ($busqueda): void {
                        $cliente
                            ->where('razon_social', 'like', "%{$busqueda}%")
                            ->orWhere('ruc', 'like', "%{$busqueda}%");
                    })
                    ->orWhereHas(
                        'vehiculo',
                        fn($vehiculo) => $vehiculo
                            ->where(
                                'placa',
                                'like',
                                "%{$busqueda}%"
                            )
                    );
            });
        }

        if (! empty($filtros['tipo'])) {
            $query->where('tipo_orden_id', $filtros['tipo']);
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (! empty($filtros['desde'])) {
            $query->whereDate('fecha_apertura', '>=', $filtros['desde']);
        }

        if (! empty($filtros['hasta'])) {
            $query->whereDate('fecha_apertura', '<=', $filtros['hasta']);
        }

        $ordenes = $query
            ->orderByDesc('fecha_apertura')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $resumen = OrdenOperacion::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN estado = 'ABIERTA' THEN 1 ELSE 0 END) as abiertas")
            ->selectRaw("SUM(CASE WHEN estado = 'EN_PROCESO' THEN 1 ELSE 0 END) as en_proceso")
            ->selectRaw("SUM(CASE WHEN estado = 'CERRADA' THEN 1 ELSE 0 END) as cerradas")
            ->first();

        $tipos = TipoOrden::query()
            ->where('estado', true)
            ->orderBy('codigo')
            ->get();

        return view('ordenes_operacion.index', compact('ordenes', 'resumen', 'tipos'));
    }

    public function create(Request $request): View
    {
        return view(
            'ordenes_operacion.create',
            $this->catalogosFormulario($request->user())
        );
    }

    public function store(StoreOrdenOperacionRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $tipoSolicitado = TipoOrden::query()->findOrFail($datos['tipo_orden_id']);

        abort_unless(
            $this->puedeCrearTipo($request->user(), $tipoSolicitado->codigo),
            403,
            'Tu perfil no puede crear ese tipo de orden.'
        );

        $orden = DB::transaction(function () use ($datos, $request): OrdenOperacion {
            $tipo = TipoOrden::query()->lockForUpdate()->findOrFail($datos['tipo_orden_id']);
            $anio = (int) date('Y', strtotime($datos['fecha_apertura']));

            $ultimo = OrdenOperacion::query()
                ->where('tipo_orden_id', $tipo->id)
                ->where('anio', $anio)
                ->lockForUpdate()
                ->max('numero_correlativo');

            $correlativo = ((int) $ultimo) + 1;
            $codigo = sprintf('%s-%03d-%02d', $tipo->codigo, $correlativo, $anio % 100);

            return OrdenOperacion::create([
                ...$datos,
                'codigo_orden' => $codigo,
                'numero_correlativo' => $correlativo,
                'anio' => $anio,
                'estado' => 'ABIERTA',
                'creado_por' => $request->user()->id,
            ]);
        });

        return redirect()
            ->route('ordenes-operacion.show', $orden->id)
            ->with('success', "Orden {$orden->codigo_orden} registrada correctamente.");
    }

    public function show(OrdenOperacion $ordenOperacion): View
    {
        $ordenOperacion->load([
            'tipoOrden',
            'cliente',
            'clienteDireccion',
            'vehiculo',
            'creador',
            'anulador',
            'requisiciones' => fn($query) => $query->latest('fecha_solicitud')->limit(8),
            'notasSalida' => fn($query) => $query->latest('fecha_salida')->limit(8),
        ])->loadCount(['requisiciones', 'notasSalida']);

        return view('ordenes_operacion.show', ['orden' => $ordenOperacion]);
    }

    public function edit(
        Request $request,
        OrdenOperacion $ordenOperacion
    ): View|RedirectResponse {
        abort_unless(
            $this->puedeEditarTipo($request->user(), $ordenOperacion),
            403,
            'Tu perfil no puede editar esta orden.'
        );

        if (! $ordenOperacion->puedeEditar()) {
            return redirect()
                ->route('ordenes-operacion.show', $ordenOperacion->id)
                ->with('error', 'Las órdenes cerradas o anuladas son de solo lectura.');
        }

        return view('ordenes_operacion.edit', [
            'orden' => $ordenOperacion,
            ...$this->catalogosFormulario($request->user()),
        ]);
    }

    public function update(
        UpdateOrdenOperacionRequest $request,
        OrdenOperacion $ordenOperacion
    ): RedirectResponse {
        abort_unless(
            $this->puedeEditarTipo($request->user(), $ordenOperacion),
            403,
            'Tu perfil no puede editar esta orden.'
        );

        if (! $ordenOperacion->puedeEditar()) {
            return redirect()
                ->route('ordenes-operacion.show', $ordenOperacion->id)
                ->with('error', 'La orden ya no admite modificaciones.');
        }

        $ordenOperacion->update($request->validated());

        return redirect()
            ->route('ordenes-operacion.show', $ordenOperacion->id)
            ->with('success', "Orden {$ordenOperacion->codigo_orden} actualizada.");
    }

    public function iniciar(
        Request $request,
        OrdenOperacion $ordenOperacion
    ): RedirectResponse {
        abort_unless(
            $request->user()->puede(P::ORDENES_GESTIONAR_ESTADO),
            403
        );

        if (! $ordenOperacion->estaAbierta()) {
            return back()->with('error', 'Solo una orden abierta puede iniciarse.');
        }

        $ordenOperacion->update(['estado' => 'EN_PROCESO']);

        return back()->with('success', "La orden {$ordenOperacion->codigo_orden} está en proceso.");
    }

    public function cerrar(
        Request $request,
        OrdenOperacion $ordenOperacion
    ): RedirectResponse {
        abort_unless(
            $request->user()->puede(P::ORDENES_GESTIONAR_ESTADO),
            403
        );

        if ($ordenOperacion->estaCerrada() || $ordenOperacion->estaAnulada()) {
            return back()->with('error', 'La orden ya no puede cerrarse.');
        }

        $ordenOperacion->update([
            'estado' => 'CERRADA',
            'cerrado_en' => now(),
        ]);

        return back()->with('success', "La orden {$ordenOperacion->codigo_orden} fue cerrada.");
    }

    public function anular(
        AnularOrdenOperacionRequest $request,
        OrdenOperacion $ordenOperacion
    ): RedirectResponse {
        abort_unless(
            $this->puedeAnularTipo($request->user(), $ordenOperacion),
            403,
            'Tu perfil no puede anular esta orden.'
        );

        if ($ordenOperacion->estaCerrada() || $ordenOperacion->estaAnulada()) {
            return back()->with('error', 'La orden no puede anularse en su estado actual.');
        }

        $salidasConfirmadas = $ordenOperacion->notasSalida()
            ->where('estado', 'CONFIRMADA')
            ->exists();

        if ($salidasConfirmadas) {
            return back()->with(
                'error',
                'No se puede anular una orden con salidas confirmadas. Anula primero esas notas.'
            );
        }

        $requisicionesActivas = $ordenOperacion->requisiciones()
            ->where('estado', '!=', 'ANULADA')
            ->exists();

        if ($requisicionesActivas) {
            return back()->with(
                'error',
                'No se puede anular una orden con requisiciones activas.'
            );
        }

        $ordenOperacion->update([
            'estado' => 'ANULADA',
            'anulado_por' => $request->user()->id,
            'anulado_en' => now(),
            'motivo_anulacion' => $request->validated('motivo_anulacion'),
        ]);

        return back()->with('success', "La orden {$ordenOperacion->codigo_orden} fue anulada.");
    }

    private function catalogosFormulario(User $usuario): array
    {
        $tiposPermitidos = $this->tiposPermitidosParaCrear($usuario);

        return [
            'tipos' => TipoOrden::query()
                ->where('estado', true)
                ->whereIn('codigo', $tiposPermitidos)
                ->orderBy('codigo')
                ->get(),
            'clientes' => Cliente::query()
                ->where('estado', true)
                ->orderBy('razon_social')
                ->get(),
            'direcciones' => ClienteDireccion::query()
                ->where('estado', true)
                ->orderByDesc('es_principal')
                ->orderBy('destino')
                ->get(),
            'vehiculos' => Vehiculo::query()
                ->where('estado', true)
                ->orderBy('placa')
                ->get(),
        ];
    }

    private function tiposPermitidosParaCrear(User $usuario): array
    {
        if ($usuario->esAdministrador()) {
            return ['OP', 'OM', 'OS', 'OV'];
        }

        $tipos = [];

        if ($usuario->puede(P::ORDENES_CREAR_COMERCIAL)) {
            $tipos = [...$tipos, 'OP', 'OM', 'OS'];
        }

        if ($usuario->puede(P::ORDENES_CREAR_VENTA)) {
            $tipos[] = 'OV';
        }

        return array_values(array_unique($tipos));
    }

    private function puedeCrearTipo(User $usuario, string $codigo): bool
    {
        if ($usuario->esAdministrador()) {
            return true;
        }

        return $codigo === 'OV'
            ? $usuario->puede(P::ORDENES_CREAR_VENTA)
            : in_array($codigo, ['OP', 'OM', 'OS'], true)
            && $usuario->puede(P::ORDENES_CREAR_COMERCIAL);
    }

    private function puedeEditarTipo(
        User $usuario,
        OrdenOperacion $orden
    ): bool {
        if ($usuario->esAdministrador()) {
            return true;
        }

        return $orden->tipoOrden?->codigo === 'OV'
            ? $usuario->puede(P::ORDENES_EDITAR_VENTA)
            : $usuario->puede(P::ORDENES_EDITAR_COMERCIAL);
    }

    private function puedeAnularTipo(
        User $usuario,
        OrdenOperacion $orden
    ): bool {
        if ($usuario->esAdministrador()) {
            return true;
        }

        return $orden->tipoOrden?->codigo === 'OV'
            ? $usuario->puede(P::ORDENES_ANULAR_VENTA)
            : $usuario->puede(P::ORDENES_ANULAR_COMERCIAL);
    }
}
