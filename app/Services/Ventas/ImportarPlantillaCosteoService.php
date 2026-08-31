<?php

namespace App\Services\Ventas;

use App\Models\CotizacionPresupuesto;
use App\Models\ImportacionPlantillaCosteo;
use App\Models\ImportacionPlantillaCosteoPartida;
use App\Models\PlantillaCosteo;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportarPlantillaCosteoService
{
    public function __construct(
        private readonly ExtractorPlantillaCosteoExcel $extractor
    ) {}

    public function crearBorrador(
        string $rutaAbsoluta,
        array $archivo,
        array $datos,
        User $usuario
    ): ImportacionPlantillaCosteo {
        $resultado = $this->extractor->extraer($rutaAbsoluta);
        $resultado['partidas'] = collect($resultado['partidas'])
            ->map(function (array $partida): array {
                if (($partida['tipo_costo'] ?? null) === 'SERVICIO_TERCERO') {
                    $partida['ejecucion_servicio'] = 'POR_DEFINIR';
                    $partida['estado_vinculacion'] = 'PENDIENTE';
                }

                return $partida;
            })
            ->all();

        return DB::transaction(function () use ($resultado, $archivo, $datos, $usuario): ImportacionPlantillaCosteo {
            $importacion = ImportacionPlantillaCosteo::query()->create([
                'tipo_orden_id' => $datos['tipo_orden_id'],
                'nombre' => trim($datos['nombre']),
                'descripcion' => filled($datos['descripcion'] ?? null) ? trim($datos['descripcion']) : null,
                'hoja' => $resultado['hoja'],
                'nombre_original' => $archivo['nombre_original'],
                'ruta_archivo' => $archivo['ruta_archivo'],
                'mime_type' => $archivo['mime_type'] ?? null,
                'advertencias' => $resultado['advertencias'],
                'estado' => 'BORRADOR',
                'creado_por' => $usuario->id,
            ]);

            $importacion->partidas()->createMany($resultado['partidas']);

            return $importacion->load(['tipoOrden', 'partidas.producto.unidadMedida']);
        });
    }

    public function actualizarPartida(
        ImportacionPlantillaCosteoPartida $partida,
        array $datos
    ): void {
        DB::transaction(function () use ($partida, $datos): void {
            $partida = ImportacionPlantillaCosteoPartida::query()
                ->with('importacion')
                ->lockForUpdate()
                ->findOrFail($partida->id);
            $this->validarBorrador($partida->importacion);

            if (($datos['accion'] ?? 'GUARDAR') === 'OMITIR') {
                $partida->update(['omitida' => true]);
                return;
            }
            if (($datos['accion'] ?? null) === 'RESTAURAR') {
                $partida->update(['omitida' => false]);
                return;
            }

            $tipo = $datos['tipo_costo'];
            $ejecucionServicio = $tipo === 'SERVICIO_TERCERO'
                ? strtoupper(trim((string) ($datos['ejecucion_servicio'] ?? '')))
                : null;
            if (
                $tipo === 'SERVICIO_TERCERO'
                && ! in_array($ejecucionServicio, ['EXTERNO', 'INTERNO_HIDROIL'], true)
            ) {
                throw ValidationException::withMessages([
                    'ejecucion_servicio' => 'Clasifica el servicio como externo o interno HIDROIL.',
                ]);
            }
            $cantidad = round((float) $datos['cantidad'], 3);
            $producto = null;
            if ($tipo === 'MATERIAL') {
                $producto = Producto::query()
                    ->with('unidadMedida')
                    ->where('estado', true)
                    ->find($datos['producto_id'] ?? null);
                if (! $producto) {
                    throw ValidationException::withMessages([
                        'producto_id' => 'Selecciona el producto de almacén correspondiente.',
                    ]);
                }
                if (! $producto->cantidadAdmitida($cantidad)) {
                    throw ValidationException::withMessages([
                        'producto_id' => 'La cantidad del Excel tiene decimales, pero este producto no admite fraccionamiento.',
                    ]);
                }
                $unidad = CotizacionPresupuesto::unidadDeProducto($producto);
                if (! $unidad) {
                    throw ValidationException::withMessages([
                        'producto_id' => 'El producto no tiene una unidad de medida válida.',
                    ]);
                }
            } else {
                $unidad = $datos['unidad'];
                if (! in_array($unidad, CotizacionPresupuesto::unidadesParaTipo($tipo), true)) {
                    throw ValidationException::withMessages([
                        'unidad' => 'La unidad no corresponde al tipo de costo seleccionado.',
                    ]);
                }
            }

            $partida->update([
                'producto_id' => $producto?->id,
                'grupo_costo' => filled($datos['grupo_costo'] ?? null)
                    ? trim($datos['grupo_costo'])
                    : null,
                'descripcion' => trim($datos['descripcion']),
                'cantidad' => $cantidad,
                'tipo_costo' => $tipo,
                'ejecucion_servicio' => $ejecucionServicio,
                'unidad' => $unidad,
                'tipo_cambio' => round((float) $datos['tipo_cambio'], 6),
                'costo_unitario' => round((float) $datos['costo_unitario'], 4),
                'margen_porcentaje' => round((float) $datos['margen_porcentaje'], 4),
                'igv_modo' => $tipo === 'MANO_OBRA' ? 'NO_APLICA' : 'INCLUIDO',
                'estado_vinculacion' => $producto
                    ? 'VINCULADA'
                    : ($tipo === 'SERVICIO_TERCERO' ? 'REVISADA' : 'NO_APLICA'),
                'omitida' => false,
            ]);
        });
    }

    public function reanalizar(ImportacionPlantillaCosteo $importacion): int
    {
        return DB::transaction(function () use ($importacion): int {
            $importacion = ImportacionPlantillaCosteo::query()
                ->lockForUpdate()
                ->findOrFail($importacion->id);
            $this->validarBorrador($importacion);
            $vinculadas = 0;

            $importacion->partidas()
                ->where('tipo_costo', 'MATERIAL')
                ->whereNull('producto_id')
                ->where('omitida', false)
                ->lockForUpdate()
                ->get()
                ->each(function (ImportacionPlantillaCosteoPartida $partida) use (&$vinculadas): void {
                    $codigo = trim((string) $partida->codigo_referencia);
                    if ($codigo === '' || in_array(mb_strtoupper($codigo), ['IGV', 'SIN IGV'], true)) {
                        return;
                    }
                    $producto = Producto::query()
                        ->with('unidadMedida')
                        ->where('estado', true)
                        ->whereRaw('UPPER(TRIM(codigo)) = ?', [mb_strtoupper($codigo)])
                        ->first();
                    $unidad = $producto ? CotizacionPresupuesto::unidadDeProducto($producto) : null;
                    if (! $producto || ! $unidad || ! $producto->cantidadAdmitida((float) $partida->cantidad)) {
                        return;
                    }
                    $partida->update([
                        'producto_id' => $producto->id,
                        'descripcion' => $producto->descripcion,
                        'unidad' => $unidad,
                        'estado_vinculacion' => 'VINCULADA',
                    ]);
                    $vinculadas++;
                });

            return $vinculadas;
        });
    }

    public function confirmar(
        ImportacionPlantillaCosteo $importacion,
        User $usuario
    ): PlantillaCosteo {
        return DB::transaction(function () use ($importacion, $usuario): PlantillaCosteo {
            $importacion = ImportacionPlantillaCosteo::query()
                ->with(['partidas.producto.unidadMedida'])
                ->lockForUpdate()
                ->findOrFail($importacion->id);
            $this->validarBorrador($importacion);

            $partidas = $importacion->partidas->where('omitida', false)->values();
            if ($partidas->isEmpty()) {
                throw ValidationException::withMessages([
                    'importacion' => 'No hay partidas activas para crear la plantilla.',
                ]);
            }
            $pendientes = $partidas->filter(
                fn(ImportacionPlantillaCosteoPartida $partida): bool =>
                $partida->tipo_costo === 'MATERIAL' && $partida->producto_id === null
            );
            if ($pendientes->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'importacion' => 'Vincula u omite todos los materiales pendientes antes de confirmar.',
                ]);
            }
            $serviciosPendientes = $partidas->filter(
                fn(ImportacionPlantillaCosteoPartida $partida): bool =>
                $partida->tipo_costo === 'SERVICIO_TERCERO'
                    && ! in_array($partida->ejecucion_servicio, ['EXTERNO', 'INTERNO_HIDROIL'], true)
            );
            if ($serviciosPendientes->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'importacion' => 'Clasifica todos los servicios como externos o internos HIDROIL antes de confirmar.',
                ]);
            }
            if (PlantillaCosteo::query()
                ->where('tipo_orden_id', $importacion->tipo_orden_id)
                ->where('nombre', $importacion->nombre)
                ->lockForUpdate()
                ->exists()
            ) {
                throw ValidationException::withMessages([
                    'importacion' => 'Ya existe una plantilla con este nombre para el mismo tipo de orden.',
                ]);
            }

            $plantilla = PlantillaCosteo::query()->create([
                'tipo_orden_id' => $importacion->tipo_orden_id,
                'nombre' => $importacion->nombre,
                'descripcion' => $importacion->descripcion,
                'origen' => 'EXCEL',
                'activo' => true,
                'creado_por' => $usuario->id,
            ]);

            $plantilla->partidas()->createMany(
                $partidas->values()->map(
                    fn(ImportacionPlantillaCosteoPartida $partida, int $indice): array => [
                        'producto_id' => $partida->producto_id,
                        'codigo_referencia' => $partida->codigo_referencia,
                        'tipo_costo' => $partida->tipo_costo,
                        'ejecucion_servicio' => $partida->ejecucion_servicio,
                        'grupo_costo' => $partida->grupo_costo,
                        'descripcion' => $partida->descripcion,
                        'cantidad' => $partida->cantidad,
                        'unidad' => $partida->tipo_costo === 'MATERIAL'
                            ? CotizacionPresupuesto::unidadDeProducto($partida->producto)
                            : $partida->unidad,
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

            $importacion->update([
                'estado' => 'CONFIRMADA',
                'confirmado_por' => $usuario->id,
                'confirmado_en' => now(),
            ]);

            return $plantilla->load(['tipoOrden', 'partidas']);
        });
    }

    private function validarBorrador(ImportacionPlantillaCosteo $importacion): void
    {
        if (! $importacion->esBorrador()) {
            throw ValidationException::withMessages([
                'importacion' => 'La importación ya fue confirmada y no puede modificarse.',
            ]);
        }
    }
}
