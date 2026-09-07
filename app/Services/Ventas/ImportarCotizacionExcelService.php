<?php

namespace App\Services\Ventas;

use App\Models\CotizacionCliente;
use App\Models\ImportacionPlantillaCosteo;
use App\Models\User;
use App\Services\Ordenes\PlanificacionPorAreaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportarCotizacionExcelService
{
    public function __construct(private PresupuestoCotizacionService $presupuestos, private PlanificacionPorAreaService $areas) {}

    public function confirmar(ImportacionPlantillaCosteo $importacion, User $usuario): int
    {
        return DB::transaction(function () use ($importacion, $usuario): int {
            $importacion = ImportacionPlantillaCosteo::lockForUpdate()->findOrFail($importacion->id);
            abort_unless($usuario->esAdministrador() || (int) $importacion->creado_por === (int) $usuario->id, 403);
            if (! $importacion->esBorrador() || ! $importacion->cotizacion_cliente_id) {
                throw ValidationException::withMessages(['importacion' => 'Esta carga no es un borrador de cotización disponible.']);
            }
            $cotizacion = CotizacionCliente::lockForUpdate()->findOrFail($importacion->cotizacion_cliente_id);
            $this->validarDestino($cotizacion);
            if ((int) $importacion->tipo_orden_id !== (int) $cotizacion->tipo_orden_id) {
                throw ValidationException::withMessages(['importacion' => 'El tipo de orden cambió. Vuelve a cargar el archivo en la cotización correcta.']);
            }
            $partidas = $importacion->partidas()->where('omitida', false)->lockForUpdate()->get();
            if ($partidas->isEmpty()) {
                throw ValidationException::withMessages(['importacion' => 'No hay filas activas para incorporar.']);
            }
            $componente = $cotizacion->componentes()->orderBy('orden_secuencia')->first();
            foreach ($partidas as $partida) {
                if (($partida->tipo_costo === 'MATERIAL' && ! $partida->producto_id)
                    || ($partida->tipo_costo === 'SERVICIO_TERCERO' && ! in_array($partida->ejecucion_servicio, ['EXTERNO', 'INTERNO_HIDROIL'], true))) {
                    throw ValidationException::withMessages(['importacion' => 'Vincula todos los materiales y clasifica todos los servicios antes de confirmar.']);
                }
                $datos = $partida->only(['producto_id', 'tipo_costo', 'ejecucion_servicio', 'grupo_costo', 'descripcion',
                    'cantidad', 'unidad', 'moneda', 'tipo_cambio', 'costo_unitario', 'margen_porcentaje', 'carga_social_porcentaje',
                    'igv_modo', 'igv_porcentaje', 'igv_venta_porcentaje', 'observacion']);
                validator($datos, [
                    'cantidad' => 'required|numeric|gt:0|max:999999.999',
                    'costo_unitario' => 'required|numeric|gte:0|max:999999.9999',
                    'tipo_cambio' => 'required|numeric|gte:0.1|max:100',
                    'moneda' => 'required|in:PEN,USD', 'igv_modo' => 'required|in:NO_APLICA,INCLUIDO,AGREGAR',
                    'margen_porcentaje' => 'required|numeric|min:0|max:999.9999',
                    'carga_social_porcentaje' => 'required|numeric|min:0|max:999.9999',
                ])->validate();
                $datos['componente_id'] = $componente?->id;
                $datos['igv_porcentaje'] = $datos['igv_modo'] === 'NO_APLICA' ? 0 : 18;
                $datos['igv_venta_porcentaje'] = 18;
                // El servicio común aplica validación de productos/unidades, reglas de cálculo y sincronización pendiente.
                // El área se resuelve antes de registrar para conservar su jerarquía.
                $ruta = $partida->ruta_areas ?: [$partida->grupo_costo ?: 'GENERAL'];
                $padre = null;
                foreach ($ruta as $nombre) {
                    $padre = $this->areas->crearArea($cotizacion, $nombre, 'EXCEL', $padre);
                }
                $datos['cotizacion_area_id'] = $padre->id;
                $this->presupuestos->registrar($cotizacion, $datos, $usuario);
                $componente ??= $cotizacion->componentes()->orderBy('orden_secuencia')->first();
            }
            $importacion->update(['estado' => 'CONFIRMADA', 'confirmado_por' => $usuario->id, 'confirmado_en' => now()]);
            return $partidas->count();
        });
    }

    public function validarDestino(CotizacionCliente $cotizacion): void
    {
        if (! $cotizacion->esEditable() || $cotizacion->orden_operacion_id || $cotizacion->proforma_id
            || ! in_array($cotizacion->tipoOrden?->codigo, ['OM', 'OS', 'OP'], true)) {
            throw ValidationException::withMessages(['importacion' => 'Importa en una cotización directa abierta de tipo OM, OS u OP, antes de generar la orden.']);
        }
    }
}
