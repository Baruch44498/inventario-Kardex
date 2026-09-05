<?php

namespace App\Services\Ventas;

use App\Models\ClienteDireccion;
use App\Models\CotizacionCliente;
use App\Models\CotizacionComponente;
use App\Models\CotizacionPresupuesto;
use App\Models\OrdenOperacion;
use App\Models\TipoOrden;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Ordenes\MaterialRequeridoOrdenService;
use App\Services\Ordenes\PlanificacionPorAreaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConvertirCotizacionEnOrdenService
{
    public function __construct(
        private readonly PlanificacionPorAreaService $planificacion,
        private readonly MaterialRequeridoOrdenService $materialesRequeridos
    ) {}

    /**
     * @return array{principal: OrdenOperacion, servicios_internos: Collection<int, OrdenOperacion>}
     */
    public function convertir(
        CotizacionCliente $cotizacionCliente,
        string $fechaApertura,
        User $usuario
    ): array {
        return DB::transaction(function () use ($cotizacionCliente, $fechaApertura, $usuario): array {
            $cotizacion = CotizacionCliente::query()
                ->with([
                    'proforma',
                    'tipoOrden',
                    'detalles',
                    'todasLasAreas',
                    'presupuestos' => fn($query) => $query->where('estado', 'VIGENTE'),
                    'componentes.tipoOrden',
                ])
                ->lockForUpdate()
                ->findOrFail($cotizacionCliente->id);

            abort_if(
                $cotizacion->proforma_id !== null,
                422,
                'Las Proformas de Almacén solo valorizan productos y no generan órdenes.'
            );
            abort_unless(
                $cotizacion->puedeConvertirseEnOrden(),
                422,
                'La cotización ya no puede generar una orden.'
            );
            abort_if(
                $cotizacion->detalles->isEmpty()
                    || $cotizacion->detalles->contains(
                        fn($detalle): bool => (float) $detalle->precio_unitario <= 0
                    ),
                422,
                'Completa las líneas comerciales y sus precios antes de aprobar la cotización.'
            );
            abort_if(
                $cotizacion->presupuestos->isNotEmpty()
                    && $cotizacion->costeo_sincronizado_en === null,
                422,
                'La hoja de costos cambió. Sincronízala con la cotización antes de aprobar.'
            );

            $principalAnterior = $this->componentePrincipal($cotizacion);
            $tipoPrincipal = $this->tipoPrincipal($cotizacion, $principalAnterior);
            $descripcion = trim((string) (
                $cotizacion->descripcion_trabajo
                ?: $principalAnterior?->descripcion_componente
            ));
            $direccionId = $cotizacion->cliente_direccion_id
                ?: $principalAnterior?->cliente_direccion_id;
            $vehiculoId = $cotizacion->vehiculo_id
                ?: $principalAnterior?->vehiculo_id;

            abort_if($descripcion === '', 422, 'La orden principal necesita una descripción.');
            $this->validarContextoPrincipal(
                $cotizacion,
                $tipoPrincipal,
                $direccionId,
                $vehiculoId
            );
            $this->planificacion->validarServiciosClasificados($cotizacion);

            $ordenPrincipal = $this->crearOrden(
                $tipoPrincipal,
                $cotizacion,
                $fechaApertura,
                $descripcion,
                $direccionId,
                $vehiculoId,
                $usuario
            );

            $this->planificacion->congelar($cotizacion, $ordenPrincipal);
            $this->crearListaInicialMateriales($cotizacion, $ordenPrincipal, $usuario);

            $tipoServicio = TipoOrden::query()
                ->where('codigo', 'OS')
                ->where('estado', true)
                ->lockForUpdate()
                ->first();
            $serviciosInternos = $cotizacion->presupuestos
                ->where('tipo_costo', 'SERVICIO_TERCERO')
                ->where('ejecucion_servicio', 'INTERNO_HIDROIL')
                ->values();

            abort_if(
                $serviciosInternos->isNotEmpty() && ! $tipoServicio,
                422,
                'No existe un tipo de orden OS activo para los servicios internos HIDROIL.'
            );

            $ordenesServicio = collect();
            foreach ($serviciosInternos as $servicio) {
                $ordenesServicio->push($this->crearOrden(
                    $tipoServicio,
                    $cotizacion,
                    $fechaApertura,
                    trim((string) $servicio->descripcion),
                    $direccionId,
                    null,
                    $usuario,
                    $ordenPrincipal,
                    $servicio
                ));
            }

            // La relación histórica componente → orden es UNIQUE. Solo el
            // componente principal puede conservar ese enlace; los demás
            // componentes anteriores quedan como referencia de la cotización.
            $principalAnterior?->update([
                'orden_operacion_id' => $ordenPrincipal->id,
            ]);
            $cotizacion->update([
                'estado' => 'CONVERTIDA_EN_ORDEN',
                'orden_operacion_id' => $ordenPrincipal->id,
                'tipo_orden_id' => $tipoPrincipal->id,
                'cliente_direccion_id' => $direccionId,
                'vehiculo_id' => $vehiculoId,
                'descripcion_trabajo' => $descripcion,
                'cerrado_por' => $cotizacion->cerrado_por ?: $usuario->id,
                'cerrado_en' => $cotizacion->cerrado_en ?: now(),
            ]);

            if ($cotizacion->proforma) {
                $cotizacion->proforma->update(['estado' => 'CONVERTIDA_EN_ORDEN']);
            }

            return [
                'principal' => $ordenPrincipal->fresh(),
                'servicios_internos' => $ordenesServicio,
            ];
        });
    }

    private function componentePrincipal(CotizacionCliente $cotizacion): ?CotizacionComponente
    {
        if ($cotizacion->tipo_orden_id) {
            $coincidente = $cotizacion->componentes->first(
                fn(CotizacionComponente $componente): bool =>
                (int) $componente->tipo_orden_id === (int) $cotizacion->tipo_orden_id
            );
            if ($coincidente) {
                return $coincidente;
            }
        }

        return $cotizacion->componentes->first();
    }

    private function tipoPrincipal(
        CotizacionCliente $cotizacion,
        ?CotizacionComponente $componente
    ): TipoOrden {
        $tipo = $cotizacion->tipoOrden ?: $componente?->tipoOrden;

        abort_unless(
            $tipo && $tipo->estado && in_array($tipo->codigo, ['OM', 'OS', 'OP'], true),
            422,
            'Selecciona un tipo principal OM, OS u OP para la cotización.'
        );

        return $tipo;
    }

    private function validarContextoPrincipal(
        CotizacionCliente $cotizacion,
        TipoOrden $tipo,
        ?int $direccionId,
        ?int $vehiculoId
    ): void {
        abort_if(
            $tipo->codigo === 'OM' && ! $vehiculoId,
            422,
            'La orden de mantenimiento necesita un vehículo.'
        );
        abort_if(
            $tipo->codigo === 'OP' && $vehiculoId,
            422,
            'La orden de producción no admite un vehículo existente.'
        );

        if ($direccionId) {
            abort_unless(
                ClienteDireccion::query()
                    ->whereKey($direccionId)
                    ->where('cliente_id', $cotizacion->cliente_id)
                    ->where('estado', true)
                    ->exists(),
                422,
                'La ubicación elegida ya no pertenece al cliente.'
            );
        }
        if ($vehiculoId) {
            $vehiculo = Vehiculo::query()->whereKey($vehiculoId)->first();
            abort_unless(
                $vehiculo && $vehiculo->estado
                    && ($vehiculo->cliente_id === null
                        || (int) $vehiculo->cliente_id === (int) $cotizacion->cliente_id),
                422,
                'El vehículo elegido ya no pertenece al cliente.'
            );
        }
    }

    private function crearListaInicialMateriales(
        CotizacionCliente $cotizacion,
        OrdenOperacion $orden,
        User $usuario
    ): void {
        $materialesPresupuestados = $cotizacion->presupuestos
            ->where('tipo_costo', 'MATERIAL')
            ->whereNotNull('producto_id');
        $lineas = $materialesPresupuestados->isNotEmpty()
            ? $materialesPresupuestados
            : $cotizacion->detalles->whereNotNull('producto_id');

        $lineas->groupBy('producto_id')
            ->map(fn(Collection $filas): float => round((float) $filas->sum('cantidad'), 3))
            ->each(function (float $cantidad, int|string $productoId) use (
                $cotizacion,
                $orden,
                $usuario
            ): void {
                if ($cantidad <= 0) {
                    return;
                }
                $this->materialesRequeridos->agregar(
                    $orden,
                    (int) $productoId,
                    $cantidad,
                    "Lista estimada de {$cotizacion->codigo} consolidada para la orden principal.",
                    $usuario
                );
            });
    }

    private function crearOrden(
        TipoOrden $tipo,
        CotizacionCliente $cotizacion,
        string $fechaApertura,
        string $descripcion,
        ?int $direccionId,
        ?int $vehiculoId,
        User $usuario,
        ?OrdenOperacion $ordenPadre = null,
        ?CotizacionPresupuesto $servicioOrigen = null
    ): OrdenOperacion {
        $anio = (int) date('Y', strtotime($fechaApertura));
        $ultimo = OrdenOperacion::query()
            ->where('tipo_orden_id', $tipo->id)
            ->where('anio', $anio)
            ->lockForUpdate()
            ->max('numero_correlativo');
        $correlativo = ((int) $ultimo) + 1;

        return OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipo->id,
            'cotizacion_cliente_id' => $cotizacion->id,
            'orden_padre_id' => $ordenPadre?->id,
            'presupuesto_servicio_origen_id' => $servicioOrigen?->id,
            'cliente_id' => $cotizacion->cliente_id,
            'cliente_direccion_id' => $direccionId,
            'vehiculo_id' => $vehiculoId,
            'codigo_orden' => sprintf(
                '%s-%03d-%02d',
                $tipo->codigo,
                $correlativo,
                $anio % 100
            ),
            'numero_correlativo' => $correlativo,
            'anio' => $anio,
            'fecha_apertura' => $fechaApertura,
            'descripcion' => $descripcion,
            'estado' => 'ABIERTA',
            'creado_por' => $usuario->id,
        ]);
    }
}
