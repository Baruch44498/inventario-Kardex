<?php

namespace App\Services\Ventas;

use App\Models\CotizacionComponente;
use App\Models\CotizacionCliente;
use App\Models\CotizacionPresupuesto;
use App\Models\PlantillaCosteo;
use App\Models\PlantillaCosteoPartida;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlantillaCosteoService
{
    public function __construct(
        private readonly PresupuestoCotizacionService $presupuestos,
        private readonly EstructuraPlantillaCosteoService $estructura
    ) {}

    public function guardarDesdeComponente(
        CotizacionComponente $componente,
        string $nombre,
        ?string $descripcion,
        User $usuario
    ): PlantillaCosteo {
        return DB::transaction(function () use (
            $componente,
            $nombre,
            $descripcion,
            $usuario
        ): PlantillaCosteo {
            $componente = CotizacionComponente::query()
                ->with(['cotizacionCliente', 'tipoOrden'])
                ->lockForUpdate()
                ->findOrFail($componente->id);
            $this->validarComponenteEditable($componente);

            $cotizacion = CotizacionCliente::query()->lockForUpdate()->findOrFail($componente->cotizacion_cliente_id);
            $componente->setRelation('cotizacionCliente', $cotizacion);
            $this->validarComponenteEditable($componente);
            $tipoOrdenId = $cotizacion->tipo_orden_id ?: $cotizacion->componentes()->firstOrFail()->tipo_orden_id;

            $partidas = CotizacionPresupuesto::query()
                ->with(['producto', 'area'])
                ->where('cotizacion_cliente_id', $componente->cotizacion_cliente_id)
                ->where('estado', 'VIGENTE')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($partidas->isEmpty()) {
                throw ValidationException::withMessages([
                    'nombre' => 'Carga al menos una partida de costos antes de guardar la plantilla.',
                ]);
            }

            $nombre = trim($nombre);
            if (PlantillaCosteo::query()
                ->where('tipo_orden_id', $tipoOrdenId)
                ->where('nombre', $nombre)
                ->lockForUpdate()
                ->exists()
            ) {
                throw ValidationException::withMessages([
                    'nombre' => 'Ya existe una plantilla con este nombre para el mismo tipo de orden.',
                ]);
            }

            $plantilla = PlantillaCosteo::query()->create([
                'tipo_orden_id' => $tipoOrdenId,
                'nombre' => $nombre,
                'descripcion' => filled($descripcion) ? trim($descripcion) : null,
                'origen' => 'HOJA_COSTOS',
                'activo' => true,
                'creado_por' => $usuario->id,
            ]);

            $areas = $this->estructura->guardarAreas($cotizacion, $plantilla);
            $plantilla->partidas()->createMany(
                $partidas->values()->map(
                    fn(CotizacionPresupuesto $partida, int $indice): array => [
                        'producto_id' => $partida->producto_id,
                        'plantilla_area_id' => ($areas[$partida->cotizacion_area_id] ?? null)?->id,
                        'codigo_referencia' => $partida->producto?->codigo,
                        'tipo_costo' => $partida->tipo_costo,
                        'ejecucion_servicio' => $partida->ejecucion_servicio,
                        'grupo_costo' => $partida->area?->nombre ?: $partida->grupo_costo,
                        'descripcion' => $partida->descripcion,
                        'cantidad' => $partida->cantidad,
                        'unidad' => $partida->unidad,
                        'moneda' => $partida->moneda,
                        'tipo_cambio' => $partida->tipo_cambio,
                        'costo_unitario' => $partida->costo_unitario,
                        'margen_porcentaje' => $partida->margen_porcentaje,
                        'carga_social_porcentaje' => $partida->carga_social_porcentaje,
                        'igv_modo' => $partida->igv_modo,
                        'igv_porcentaje' => $partida->igv_porcentaje,
                        'igv_venta_porcentaje' => $partida->igv_venta_porcentaje,
                        'observacion' => $partida->observacion,
                        'orden_secuencia' => $indice + 1,
                    ]
                )->all()
            );

            $this->estructura->completarGrupos($plantilla);

            return $plantilla->load(['tipoOrden', 'partidas', 'areas']);
        });
    }

    public function aplicar(
        PlantillaCosteo $plantilla,
        CotizacionComponente $componente,
        User $usuario
    ): int {
        return DB::transaction(function () use (
            $plantilla,
            $componente,
            $usuario
        ): int {
            $componente = CotizacionComponente::query()
                ->with(['cotizacionCliente', 'tipoOrden'])
                ->lockForUpdate()
                ->findOrFail($componente->id);
            $this->validarComponenteEditable($componente);

            $cotizacion = CotizacionCliente::query()->lockForUpdate()->findOrFail($componente->cotizacion_cliente_id);
            $tipoOrdenId = $cotizacion->tipo_orden_id ?: $cotizacion->componentes()->firstOrFail()->tipo_orden_id;
            $componente = $cotizacion->componentes()->firstOrFail();
            $componente->setRelation('cotizacionCliente', $cotizacion);
            $this->validarComponenteEditable($componente);
            $plantilla = PlantillaCosteo::query()
                ->with('partidas.producto.unidadMedida')
                ->lockForUpdate()
                ->findOrFail($plantilla->id);

            if (! $plantilla->activo) {
                throw ValidationException::withMessages([
                    'plantilla_id' => 'La plantilla seleccionada ya no está activa.',
                ]);
            }
            if ((int) $plantilla->tipo_orden_id !== (int) $tipoOrdenId) {
                throw ValidationException::withMessages([
                    'plantilla_id' => 'La plantilla debe corresponder al tipo OM, OS u OP de la orden principal.',
                ]);
            }
            if ($plantilla->partidas->isEmpty()) {
                throw ValidationException::withMessages([
                    'plantilla_id' => 'La plantilla seleccionada no contiene partidas.',
                ]);
            }
            if ($plantilla->partidas->contains(
                fn(PlantillaCosteoPartida $partida): bool =>
                $partida->tipo_costo === 'SERVICIO_TERCERO'
                    && ! in_array($partida->ejecucion_servicio, ['EXTERNO', 'INTERNO_HIDROIL'], true)
            )) {
                throw ValidationException::withMessages([
                    'plantilla_id' => 'La plantilla contiene servicios sin clasificar como externos o internos HIDROIL.',
                ]);
            }

            $yaTieneCostos = CotizacionPresupuesto::query()
                ->where('cotizacion_cliente_id', $componente->cotizacion_cliente_id)
                ->where('estado', 'VIGENTE')
                ->exists();
            if ($yaTieneCostos) {
                throw ValidationException::withMessages([
                    'plantilla_id' => 'La plantilla solo puede aplicarse a una cotización sin costos vigentes para evitar duplicados.',
                ]);
            }

            $this->estructura->completarGrupos($plantilla);
            $plantilla->load('partidas.producto.unidadMedida');
            $areas = $this->estructura->aplicarAreas($plantilla, $cotizacion);
            $tipoCambioComponente = (float) ($cotizacion->tipo_cambio ?: $componente->tipo_cambio_comparacion);
            $margenCotizacion = (float) $componente->cotizacionCliente->margen_cliente_porcentaje;
            $lineas = $plantilla->partidas->map(
                function (PlantillaCosteoPartida $partida) use (
                    $componente,
                    $plantilla,
                    $usuario,
                    $tipoCambioComponente,
                    $margenCotizacion,
                    $areas
                ): array {
                    $datos = [
                        'componente_id' => $componente->id,
                        'producto_id' => $partida->producto_id,
                        'tipo_costo' => $partida->tipo_costo,
                        'ejecucion_servicio' => $partida->ejecucion_servicio,
                        'grupo_costo' => $partida->grupo_costo,
                        'descripcion' => $partida->descripcion,
                        'cantidad' => $partida->cantidad,
                        'unidad' => $partida->unidad,
                        'moneda' => $partida->moneda,
                        'costo_unitario' => $partida->costo_unitario,
                        'tipo_cambio' => $tipoCambioComponente > 0
                            ? $tipoCambioComponente
                            : $partida->tipo_cambio,
                        'margen_porcentaje' => $margenCotizacion,
                        'carga_social_porcentaje' => $partida->carga_social_porcentaje,
                        'igv_modo' => $partida->igv_modo,
                        'igv_porcentaje' => $partida->igv_modo === 'NO_APLICA'
                            ? 0
                            : CotizacionPresupuesto::IGV_PORCENTAJE,
                        'igv_venta_porcentaje' => CotizacionPresupuesto::IGV_PORCENTAJE,
                        'observacion' => $partida->observacion,
                    ];
                    if ($partida->plantilla_area_id) {
                        $area = $areas[$partida->plantilla_area_id] ?? null;
                        if (! $area) {
                            throw ValidationException::withMessages(['plantilla_id' => 'Una partida apunta a un área ajena a esta plantilla.']);
                        }
                        $datos['cotizacion_area_id'] = $area->id;
                        $datos['grupo_costo'] = $area->nombre;
                    } else {
                        $datos = $this->presupuestos->completarEstructura(
                            $componente->cotizacionCliente,
                            $datos,
                            $plantilla->origen === 'EXCEL' ? 'EXCEL' : 'MANUAL'
                        );
                    }

                    return [
                        ...$this->presupuestos->prepararLinea(
                            $datos,
                            $partida->producto
                        ),
                        'estado' => 'VIGENTE',
                        'registrado_por' => $usuario->id,
                        'registrado_en' => now(),
                    ];
                }
            )->all();

            $componente->cotizacionCliente->presupuestos()->createMany($lineas);
            if ($componente->cotizacionCliente->costeo_sincronizado_en !== null) {
                $componente->cotizacionCliente->update([
                    'costeo_sincronizado_en' => null,
                ]);
            }

            return count($lineas);
        });
    }

    private function validarComponenteEditable(
        CotizacionComponente $componente
    ): void {
        if (
            $componente->cotizacionCliente->proforma_id !== null
            || ! $componente->cotizacionCliente->esEditable()
            || $componente->orden_operacion_id !== null
        ) {
            throw ValidationException::withMessages([
                'plantilla_id' => 'Las plantillas solo se gestionan en componentes editables de una cotización operativa.',
            ]);
        }
    }
}
