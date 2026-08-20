<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularOrdenOperacionRequest;
use App\Http\Requests\AnularCostoDirectoOrdenRequest;
use App\Http\Requests\CerrarOrdenOperacionRequest;
use App\Http\Requests\RegistrarAvanceOrdenOperacionRequest;
use App\Http\Requests\StoreCostoDirectoOrdenRequest;
use App\Http\Requests\UpdateOrdenOperacionRequest;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\CostoDirectoOrden;
use App\Models\OrdenOperacion;
use App\Models\Proveedor;
use App\Models\TipoOrden;
use App\Models\Vehiculo;
use App\Models\User;
use App\Support\PermisoSistema as P;
use App\Services\Inventario\DisponibilidadMaterialService;
use App\Services\Inventario\ReservaMaterialService;
use App\Services\Ordenes\MaterialRequeridoOrdenService;
use App\Services\Ordenes\CerrarOrdenOperacionService;
use App\Services\Ordenes\RegistrarAvanceOrdenService;
use App\Services\Ordenes\RegistrarCostoDirectoOrdenService;
use App\Services\Ordenes\ResumenEjecucionOrdenService;
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
            'estado' => ['nullable', 'in:ACTIVAS,ABIERTA,EN_PROCESO,CERRADA,ANULADA'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $query = OrdenOperacion::query()
            ->with([
                'tipoOrden',
                'cliente',
                'vehiculo',
                'creador',
                'ultimoAvance',
                'cotizacionComponente',
                'cotizacionCliente' => fn($cotizacion) => $cotizacion
                    ->withCount('detalles'),
                'cotizacionOrigen' => fn($cotizacion) => $cotizacion
                    ->withCount('detalles'),
            ])
            ->withCount(['requisiciones', 'notasSalida', 'detallesCotizados']);

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

        if (($filtros['estado'] ?? null) === 'ACTIVAS') {
            $query->whereIn('estado', ['ABIERTA', 'EN_PROCESO']);
        } elseif (! empty($filtros['estado'])) {
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
        $ordenes->getCollection()->each(function (OrdenOperacion $orden): void {
            if ($orden->cotizacionOrigen) {
                $orden->setRelation('cotizacionCliente', $orden->cotizacionOrigen);
            }
            if ($orden->cotizacion_cliente_id && $orden->cotizacionCliente) {
                $orden->cotizacionCliente->setAttribute(
                    'detalles_count',
                    (int) $orden->detalles_cotizados_count
                );
            }
        });

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
        DisponibilidadMaterialService $disponibilidad,
        ResumenEjecucionOrdenService $resumenEjecucion
    ): View {
        $ordenOperacion->load([
            'tipoOrden',
            'cliente',
            'clienteDireccion',
            'vehiculo',
            'creador',
            'iniciador',
            'cerrador',
            'anulador',
            'cotizacionCliente.detalles.producto',
            'cotizacionCliente.proforma',
            'cotizacionOrigen.detalles.producto',
            'cotizacionOrigen.proforma',
            'cotizacionComponente',
            'requisiciones' => fn($query) => $query->latest('fecha_solicitud')->limit(8),
            'notasSalida' => fn($query) => $query->latest('fecha_salida')->limit(8),
            'materialesRequeridos' => fn($query) => $query
                ->with([
                    'producto.unidadMedida',
                    'creador',
                    'actualizadoPor',
                    'historial.registradoPor',
                ])
                ->orderByDesc('updated_at'),
            'reservasMateriales' => fn($query) => $query
                ->with(['producto.unidadMedida', 'reservadoPor', 'actualizadoPor'])
                ->orderByRaw("CASE WHEN estado = 'ACTIVA' THEN 0 ELSE 1 END")
                ->orderByDesc('updated_at'),
            'avances' => fn($query) => $query
                ->with('registradoPor')
                ->latest('id')
                ->limit(10),
            'costosDirectos' => fn($query) => $query
                ->with(['proveedor', 'registradoPor', 'anuladoPor'])
                ->latest('id')
                ->limit(20),
        ])->loadCount(['requisiciones', 'notasSalida']);
        if ($ordenOperacion->cotizacionOrigen) {
            $ordenOperacion->setRelation('cotizacionCliente', $ordenOperacion->cotizacionOrigen);
        }
        if ($ordenOperacion->cotizacionComponente && $ordenOperacion->cotizacionCliente) {
            $ordenOperacion->cotizacionCliente->setRelation(
                'detalles',
                $ordenOperacion->cotizacionCliente->detalles
                    ->where('componente_id', $ordenOperacion->cotizacionComponente->id)
                    ->values()
            );
        }

        $cantidadesEntregadas = DB::table('nota_salida_detalles as d')
            ->join('notas_salida as n', 'n.id', '=', 'd.nota_salida_id')
            ->where('n.orden_operacion_id', $ordenOperacion->id)
            ->where('n.estado', 'CONFIRMADA')
            ->where('d.tratamiento', 'CONSUMO')
            ->whereIn('d.producto_id', $ordenOperacion->materialesRequeridos->pluck('producto_id'))
            ->groupBy('d.producto_id')
            ->selectRaw('d.producto_id, SUM(d.cantidad) as cantidad_entregada')
            ->pluck('cantidad_entregada', 'd.producto_id');

        foreach ($ordenOperacion->materialesRequeridos as $material) {
            $entregada = round((float) ($cantidadesEntregadas[$material->producto_id] ?? 0), 3);
            $requerida = round((float) $material->cantidad_requerida, 3);
            $pendiente = max(0, round($requerida - $entregada, 3));

            $material->setAttribute('cantidad_entregada', $entregada);
            $material->setAttribute('cantidad_pendiente', $pendiente);
            $material->setAttribute('cantidad_inicial', $material->cantidadInicial());
            $material->setAttribute('variacion_acumulada', $material->variacionAcumulada());
            $material->setAttribute(
                'estado_requerimiento',
                $entregada > $requerida + 0.0001
                    ? 'EXCEDIDO'
                    : ($pendiente <= 0.0001 ? 'ATENDIDO' : ($entregada > 0.0001 ? 'PARCIAL' : 'PENDIENTE'))
            );
        }

        $productosPlanificados = $ordenOperacion->reservasMateriales->pluck('producto_id')
            ->merge($ordenOperacion->materialesRequeridos->pluck('producto_id'))
            ->unique();

        $resumenes = $disponibilidad->resumenesProductos(
            $productosPlanificados,
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
            'resumenEjecucion' => $resumenEjecucion->construir(
                $ordenOperacion,
                request()->user()->puede(P::ORDENES_VER_COSTOS)
            ),
            'proveedoresCostos' => request()->user()->puede(P::ORDENES_GESTIONAR_COSTOS)
                ? Proveedor::query()->activos()->orderBy('razon_social')->get()
                : collect(),
        ]);
    }

    public function registrarAvance(
        RegistrarAvanceOrdenOperacionRequest $request,
        OrdenOperacion $ordenOperacion,
        RegistrarAvanceOrdenService $avances
    ): RedirectResponse {
        $avance = $avances->registrar(
            $ordenOperacion,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->to(route('ordenes-operacion.show', $ordenOperacion) . '#avance-operativo')
            ->with(
                'success',
                'Avance de ' . number_format((float) $avance->porcentaje, 2) . '% registrado.'
            );
    }

    public function registrarCosto(
        StoreCostoDirectoOrdenRequest $request,
        OrdenOperacion $ordenOperacion,
        RegistrarCostoDirectoOrdenService $costos
    ): RedirectResponse {
        $costo = $costos->registrar(
            $ordenOperacion,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->to(route('ordenes-operacion.show', $ordenOperacion) . '#costos-directos')
            ->with(
                'success',
                'Costo directo por S/ ' . number_format((float) $costo->total_soles, 2) . ' registrado.'
            );
    }

    public function anularCosto(
        AnularCostoDirectoOrdenRequest $request,
        CostoDirectoOrden $costoDirecto,
        RegistrarCostoDirectoOrdenService $costos
    ): RedirectResponse {
        $costos->anular(
            $costoDirecto,
            $request->validated('motivo_anulacion'),
            $request->user()
        );

        return redirect()
            ->to(route('ordenes-operacion.show', $costoDirecto->orden_operacion_id) . '#costos-directos')
            ->with('success', 'Costo directo anulado. El registro permanece visible para auditoría.');
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

        if ($ordenOperacion->cotizacion_cliente_id || $ordenOperacion->cotizacionCliente()->exists()) {
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
            )
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

        if ($ordenOperacion->cotizacion_cliente_id || $ordenOperacion->cotizacionCliente()->exists()) {
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
        OrdenOperacion $ordenOperacion,
        MaterialRequeridoOrdenService $materiales,
        ReservaMaterialService $reservas
    ): RedirectResponse {
        abort_unless(
            $request->user()->puede(P::ORDENES_GESTIONAR_ESTADO),
            403
        );

        if (! $ordenOperacion->estaAbierta()) {
            return back()->with('error', 'Solo una orden abierta puede activarse.');
        }

        $resultado = DB::transaction(function () use (
            $ordenOperacion,
            $request,
            $materiales,
            $reservas
        ): array {
            $orden = OrdenOperacion::query()
                ->with('tipoOrden')
                ->lockForUpdate()
                ->findOrFail($ordenOperacion->id);

            if (! $orden->estaAbierta()) {
                abort(422, 'La orden ya no está disponible para activación.');
            }

            $admitePlanificacion = in_array($orden->tipoOrden?->codigo, ['OM', 'OS', 'OP'], true);
            $congelados = 0;

            if ($admitePlanificacion) {
                $congelados = $materiales->congelarPrevision($orden, $request->user());
            }

            $orden->update([
                'estado' => 'EN_PROCESO',
                'iniciado_en' => now(),
                'iniciado_por' => $request->user()->id,
            ]);

            $sincronizacion = $admitePlanificacion
                ? $reservas->sincronizarConRequerimientos($orden->fresh(['tipoOrden']), $request->user())
                : ['creadas' => 0, 'aumentadas' => 0, 'liberadas' => 0, 'sin_cambios' => 0];

            return [
                'congelados' => $congelados,
                'sincronizacion' => $sincronizacion,
            ];
        });

        $sincronizadas = (int) $resultado['sincronizacion']['creadas']
            + (int) $resultado['sincronizacion']['aumentadas'];

        $mensaje = "La orden {$ordenOperacion->codigo_orden} fue activada.";
        if ($resultado['congelados'] > 0) {
            $mensaje .= " Se congeló la previsión de {$resultado['congelados']} material(es).";
        }
        if ($sincronizadas > 0) {
            $mensaje .= " Las reservas quedaron sincronizadas automáticamente.";
        } elseif ($resultado['congelados'] === 0) {
            $mensaje .= ' No había materiales requeridos para reservar.';
        }
        $mensaje .= ' El stock físico no fue descontado.';

        return back()->with('success', $mensaje);
    }

    public function cerrar(
        CerrarOrdenOperacionRequest $request,
        OrdenOperacion $ordenOperacion,
        CerrarOrdenOperacionService $cierre
    ): RedirectResponse {
        abort_unless(
            $request->user()->puede(P::ORDENES_GESTIONAR_ESTADO),
            403
        );

        $resultado = $cierre->cerrar(
            $ordenOperacion,
            $request->validated('observacion_cierre'),
            $request->user()
        );

        $mensaje = "La orden {$ordenOperacion->codigo_orden} fue cerrada.";
        if ($resultado['reservas_liberadas'] > 0) {
            $mensaje .= " Se liberaron {$resultado['reservas_liberadas']} reserva(s) pendiente(s).";
        }
        $mensaje .= ' El costo real y la rentabilidad quedaron congelados para auditoría.';

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
                'No se puede anular una orden con requerimientos de compra activos.'
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
