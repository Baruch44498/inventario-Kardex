<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularOrdenOperacionRequest;
use App\Http\Requests\UpdateOrdenOperacionRequest;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\OrdenOperacion;
use App\Models\TipoOrden;
use App\Models\Vehiculo;
use App\Models\User;
use App\Support\PermisoSistema as P;
use App\Services\Inventario\DisponibilidadMaterialService;
use App\Services\Inventario\ReservaMaterialService;
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
            ->with([
                'tipoOrden',
                'cliente',
                'vehiculo',
                'creador',
                'cotizacionCliente' => fn($cotizacion) => $cotizacion
                    ->withCount('detalles'),
            ])
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

        $codigosVisibles = ['OM', 'OS', 'OP'];
        $existenOvHistoricas = OrdenOperacion::query()
            ->whereHas('tipoOrden', fn($query) => $query->where('codigo', 'OV'))
            ->exists();

        if ($existenOvHistoricas) {
            $codigosVisibles[] = 'OV';
        }

        $tipos = TipoOrden::query()
            ->where('estado', true)
            ->whereIn('codigo', $codigosVisibles)
            ->orderBy('codigo')
            ->get();

        return view('ordenes_operacion.index', compact('ordenes', 'resumen', 'tipos'));
    }

    public function create(Request $request): RedirectResponse
    {
        $ruta = $request->user()->puede(P::PROFORMAS_COTIZAR)
            ? 'cotizaciones-cliente.create'
            : 'proformas.create';

        return redirect()
            ->route($ruta)
            ->with(
                'info',
                'La orden ya no se registra por separado: se generará al aprobar la cotización.'
            );
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->create($request);
    }

    public function show(
        OrdenOperacion $ordenOperacion,
        DisponibilidadMaterialService $disponibilidad
    ): View {
        $ordenOperacion->load([
            'tipoOrden',
            'cliente',
            'clienteDireccion',
            'vehiculo',
            'creador',
            'anulador',
            'cotizacionCliente.detalles.producto',
            'cotizacionCliente.proforma',
            'requisiciones' => fn($query) => $query->latest('fecha_solicitud')->limit(8),
            'notasSalida' => fn($query) => $query->latest('fecha_salida')->limit(8),
            'reservasMateriales' => fn($query) => $query
                ->with(['producto.unidadMedida', 'reservadoPor', 'actualizadoPor'])
                ->orderByRaw("CASE WHEN estado = 'ACTIVA' THEN 0 ELSE 1 END")
                ->orderByDesc('updated_at'),
        ])->loadCount(['requisiciones', 'notasSalida']);

        $resumenes = $disponibilidad->resumenesProductos(
            $ordenOperacion->reservasMateriales->pluck('producto_id'),
            $ordenOperacion->id
        );

        foreach ($ordenOperacion->reservasMateriales as $reserva) {
            $reserva->setAttribute(
                'resumen_disponibilidad',
                $resumenes->get($reserva->producto_id, [])
            );
        }

        return view('ordenes_operacion.show', [
            'orden' => $ordenOperacion,
            'herramientasEnUso' => $this->herramientasEnUsoOrden($ordenOperacion->id),
        ]);
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

        if ($ordenOperacion->cotizacionCliente()->exists()) {
            return redirect()
                ->route('ordenes-operacion.show', $ordenOperacion->id)
                ->with(
                    'info',
                    'Los datos comerciales de esta orden provienen de su cotización y no se duplican.'
                );
        }

        return view('ordenes_operacion.edit', [
            'orden' => $ordenOperacion,
            ...$this->catalogosFormulario(
                $request->user(),
                (int) $request->old('cliente_id', $ordenOperacion->cliente_id) ?: null,
                (int) $request->old(
                    'cliente_direccion_id',
                    $ordenOperacion->cliente_direccion_id
                ) ?: null,
                (int) $request->old('vehiculo_id', $ordenOperacion->vehiculo_id) ?: null
            ),
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

        if ($ordenOperacion->cotizacionCliente()->exists()) {
            return redirect()
                ->route('ordenes-operacion.show', $ordenOperacion->id)
                ->with(
                    'error',
                    'No se pueden sobrescribir los datos heredados de la cotización.'
                );
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
        OrdenOperacion $ordenOperacion,
        ReservaMaterialService $reservas
    ): RedirectResponse {
        abort_unless(
            $request->user()->puede(P::ORDENES_GESTIONAR_ESTADO),
            403
        );

        if ($ordenOperacion->estaCerrada() || $ordenOperacion->estaAnulada()) {
            return back()->with('error', 'La orden ya no puede cerrarse.');
        }

        $reservasLiberadas = DB::transaction(function () use (
            $ordenOperacion,
            $request,
            $reservas
        ): int {
            $liberadas = $reservas->liberarPendientesOrden($ordenOperacion, $request->user());
            $ordenOperacion->update([
                'estado' => 'CERRADA',
                'cerrado_en' => now(),
            ]);

            return $liberadas;
        });

        $mensaje = "La orden {$ordenOperacion->codigo_orden} fue cerrada.";
        if ($reservasLiberadas > 0) {
            $mensaje .= " Se liberaron {$reservasLiberadas} reserva(s) pendiente(s).";
        }

        return back()->with('success', $mensaje);
    }

    public function anular(
        AnularOrdenOperacionRequest $request,
        OrdenOperacion $ordenOperacion,
        ReservaMaterialService $reservas
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

        DB::transaction(function () use ($ordenOperacion, $request, $reservas): void {
            $reservas->liberarPendientesOrden($ordenOperacion, $request->user());
            $ordenOperacion->update([
                'estado' => 'ANULADA',
                'anulado_por' => $request->user()->id,
                'anulado_en' => now(),
                'motivo_anulacion' => $request->validated('motivo_anulacion'),
            ]);
        });

        return back()->with('success', "La orden {$ordenOperacion->codigo_orden} fue anulada y sus reservas pendientes quedaron liberadas.");
    }

    private function catalogosFormulario(
        User $usuario,
        ?int $clienteId = null,
        ?int $direccionId = null,
        ?int $vehiculoId = null
    ): array {
        $tiposPermitidos = $this->tiposPermitidosParaCrear($usuario);

        return [
            'tipos' => TipoOrden::query()
                ->where('estado', true)
                ->whereIn('codigo', $tiposPermitidos)
                ->orderBy('codigo')
                ->get(),
            'clienteSeleccionado' => $clienteId
                ? Cliente::query()->find($clienteId)
                : null,
            'direcciones' => ClienteDireccion::query()
                ->when(
                    $clienteId,
                    fn($query) => $query->where('cliente_id', $clienteId),
                    fn($query) => $query->whereRaw('1 = 0')
                )
                ->where(function ($query) use ($direccionId): void {
                    $query->where('estado', true);

                    if ($direccionId) {
                        $query->orWhere('id', $direccionId);
                    }
                })
                ->orderByDesc('es_principal')
                ->orderBy('destino')
                ->get(),
            'vehiculos' => Vehiculo::query()
                ->where(function ($query) use ($clienteId, $vehiculoId): void {
                    $query->where(function ($disponibles) use ($clienteId): void {
                        $disponibles->where('estado', true);

                        if ($clienteId) {
                            $disponibles->where(function ($dueno) use ($clienteId): void {
                                $dueno->where('cliente_id', $clienteId)
                                    ->orWhereNull('cliente_id');
                            });
                        } else {
                            $disponibles->whereNull('cliente_id');
                        }
                    });

                    if ($vehiculoId) {
                        $query->orWhere('id', $vehiculoId);
                    }
                })
                ->orderBy('placa')
                ->get(),
        ];
    }

    private function tiposPermitidosParaCrear(User $usuario): array
    {
        if ($usuario->esAdministrador()) {
            return ['OP', 'OM', 'OS'];
        }

        $tipos = [];

        if ($usuario->puede(P::ORDENES_CREAR_COMERCIAL)) {
            $tipos = [...$tipos, 'OP', 'OM', 'OS'];
        }


        return array_values(array_unique($tipos));
    }

    private function puedeCrearTipo(User $usuario, string $codigo): bool
    {
        if ($codigo === 'OV') {
            return false;
        }

        if ($usuario->esAdministrador()) {
            return true;
        }

        return in_array($codigo, ['OP', 'OM', 'OS'], true)
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

    private function herramientasEnUsoOrden(int $ordenId)
    {
        $retornos = DB::table('nota_ingreso_detalles as d')
            ->join('notas_ingreso as n', 'n.id', '=', 'd.nota_ingreso_id')
            ->where('n.estado', 'CONFIRMADA')
            ->whereNotNull('d.nota_salida_detalle_id')
            ->groupBy('d.nota_salida_detalle_id')
            ->selectRaw('d.nota_salida_detalle_id')
            ->selectRaw('SUM(d.cantidad) as retornado');

        return DB::table('nota_salida_detalles as d')
            ->join('notas_salida as n', 'n.id', '=', 'd.nota_salida_id')
            ->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->join('unidades_medida as um', 'um.id', '=', 'p.unidad_medida_id')
            ->leftJoinSub($retornos, 'ret', fn($join) => $join
                ->on('ret.nota_salida_detalle_id', '=', 'd.id'))
            ->where('n.estado', 'CONFIRMADA')
            ->where('n.orden_operacion_id', $ordenId)
            ->where('d.tratamiento', 'USO_TEMPORAL')
            ->whereRaw('(d.cantidad - COALESCE(ret.retornado, 0)) > 0.0001')
            ->select([
                'd.id as detalle_id',
                'n.id as nota_id',
                'n.codigo as nota_codigo',
                'n.fecha_salida',
                'n.entregado_a',
                'p.codigo as producto_codigo',
                'p.descripcion as producto_descripcion',
                'um.codigo as unidad_codigo',
                DB::raw('(d.cantidad - COALESCE(ret.retornado, 0)) as pendiente'),
            ])
            ->orderByDesc('n.fecha_salida')
            ->orderByDesc('d.id')
            ->limit(12)
            ->get();
    }
}
