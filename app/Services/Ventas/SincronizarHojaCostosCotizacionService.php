<?php

namespace App\Services\Ventas;

use App\Models\CotizacionCliente;
use App\Models\CotizacionComponente;
use App\Models\CotizacionPresupuesto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SincronizarHojaCostosCotizacionService
{
    public function __construct(
        private readonly CalcularProformaService $calculador
    ) {}

    public function sincronizar(CotizacionCliente $cotizacion): array
    {
        return DB::transaction(function () use ($cotizacion): array {
            $cotizacion = CotizacionCliente::query()
                ->lockForUpdate()
                ->findOrFail($cotizacion->id);

            if (! $cotizacion->esEditable()) {
                throw ValidationException::withMessages([
                    'presupuesto' => 'Solo una cotización abierta puede actualizarse desde la hoja de costos.',
                ]);
            }
            if ($cotizacion->proforma_id !== null) {
                throw ValidationException::withMessages([
                    'presupuesto' => 'La proforma de venta directa conserva su flujo comercial original.',
                ]);
            }

            $cotizacion->load([
                'tipoOrden',
                'componentes.tipoOrden',
                'presupuestos' => fn($query) => $query
                    ->where('estado', 'VIGENTE')
                    ->with('producto.unidadMedida')
                    ->orderBy('id'),
            ]);

            $componentePrincipal = $cotizacion->tipo_orden_id
                ? $cotizacion->componentes->first(
                    fn(CotizacionComponente $componente): bool =>
                    (int) $componente->tipo_orden_id === (int) $cotizacion->tipo_orden_id
                )
                : null;
            $componentePrincipal ??= $cotizacion->componentes->first();

            if (! $componentePrincipal) {
                throw ValidationException::withMessages([
                    'presupuesto' => 'Define el contexto de la orden principal OP, OM u OS.',
                ]);
            }

            $tipoPrincipal = $cotizacion->tipoOrden ?: $componentePrincipal->tipoOrden;
            if (! in_array($tipoPrincipal?->codigo, ['OP', 'OM', 'OS'], true)) {
                throw ValidationException::withMessages([
                    'presupuesto' => 'La orden principal debe ser OP, OM u OS.',
                ]);
            }

            if ($cotizacion->presupuestos->isEmpty()) {
                throw ValidationException::withMessages([
                    'presupuesto' => 'Agrega al menos una partida a la hoja de costos.',
                ]);
            }

            $cotizacion->detalles()->delete();
            if ((int) $componentePrincipal->tipo_orden_id !== (int) $tipoPrincipal->id) {
                $componentePrincipal->setRelation('tipoOrden', $tipoPrincipal);
            }
            $entradas = collect($this->lineasComerciales(
                $cotizacion,
                $componentePrincipal,
                $cotizacion->presupuestos->values()
            ));

            $resultado = $this->calculador->calcular($entradas->all());
            $cotizacion->detalles()->createMany($resultado['detalles']);
            $cotizacion->update([
                ...$resultado['totales'],
                'costeo_sincronizado_en' => now(),
            ]);

            return [
                'lineas' => count($resultado['detalles']),
                ...$resultado['totales'],
            ];
        });
    }

    private function lineasComerciales(
        CotizacionCliente $cotizacion,
        CotizacionComponente $componente,
        Collection $partidas
    ): array {
        $tipo = $componente->tipoOrden?->codigo;

        if ($tipo !== 'OM') {
            return [$this->lineaResumida(
                $cotizacion,
                $componente,
                $partidas,
                $tipo === 'OP' ? 'PRODUCCION' : 'SERVICIO'
            )];
        }

        $materiales = $partidas
            ->where('tipo_costo', 'MATERIAL')
            ->whereNotNull('producto_id')
            ->groupBy('producto_id')
            ->map(fn(Collection $lineas) => $this->lineaMaterial(
                $cotizacion,
                $componente,
                $lineas
            ))
            ->values();
        $idsDetallados = $partidas
            ->where('tipo_costo', 'MATERIAL')
            ->whereNotNull('producto_id')
            ->pluck('id');
        $complementarios = $partidas->whereNotIn('id', $idsDetallados)->values();

        if ($complementarios->isNotEmpty()) {
            $materiales->push($this->lineaResumida(
                $cotizacion,
                $componente,
                $complementarios,
                'SERVICIO',
                'Servicio de mantenimiento'
            ));
        }

        return $materiales->all();
    }

    private function lineaMaterial(
        CotizacionCliente $cotizacion,
        CotizacionComponente $componente,
        Collection $partidas
    ): array {
        /** @var CotizacionPresupuesto $primera */
        $primera = $partidas->first();
        $producto = $primera->producto;
        $cantidad = round((float) $partidas->sum('cantidad'), 3);
        $costoNeto = $this->sumar($cotizacion, $partidas, 'costo_neto');
        $ventaNeta = $this->sumar($cotizacion, $partidas, 'precio_venta_neto');

        return $this->entradaBase($cotizacion, $componente, [
            'producto_id' => $producto?->id,
            'tipo_linea' => 'PRODUCTO',
            'codigo_producto' => $producto?->codigo ?: 'MAT-' . $primera->id,
            'descripcion' => $producto?->descripcion ?: $primera->descripcion,
            'unidad_medida' => $producto?->unidadMedida?->abreviatura
                ?: $producto?->unidadMedida?->codigo
                ?: $primera->unidadVisible(),
            'cantidad' => $cantidad,
            'costo_referencia' => round($costoNeto / $cantidad, 4),
            'precio_unitario' => round($ventaNeta / $cantidad, 4),
        ]);
    }

    private function lineaResumida(
        CotizacionCliente $cotizacion,
        CotizacionComponente $componente,
        Collection $partidas,
        string $tipoLinea,
        ?string $prefijo = null
    ): array {
        $costoNeto = $this->sumar($cotizacion, $partidas, 'costo_neto');
        $ventaNeta = $this->sumar($cotizacion, $partidas, 'precio_venta_neto');
        $tipo = $componente->tipoOrden?->codigo ?: 'TRABAJO';

        return $this->entradaBase($cotizacion, $componente, [
            'producto_id' => null,
            'tipo_linea' => $tipoLinea,
            'codigo_producto' => 'COST-' . $tipo . '-' . $componente->orden_secuencia,
            'descripcion' => trim(($prefijo ? $prefijo . ': ' : '')
                . ($cotizacion->descripcion_trabajo ?: $componente->descripcion_componente)),
            'unidad_medida' => 'SERVICIO',
            'cantidad' => 1,
            'costo_referencia' => $costoNeto,
            'precio_unitario' => $ventaNeta,
        ]);
    }

    private function entradaBase(
        CotizacionCliente $cotizacion,
        CotizacionComponente $componente,
        array $linea
    ): array {
        $costo = (float) $linea['costo_referencia'];
        $precio = (float) $linea['precio_unitario'];
        $margen = $costo > 0.0001
            ? round((($precio / $costo) - 1) * 100, 4)
            : 0.0;

        return [
            ...$linea,
            'componente_id' => $componente->id,
            'proforma_detalle_id' => null,
            'origen_costeo' => true,
            'margen_sugerido' => $margen,
            'precio_sugerido' => $precio,
            'igv_modo' => 'AGREGAR',
            'observacion' => 'Valor comercial calculado desde la hoja de costos interna.',
        ];
    }

    private function sumar(
        CotizacionCliente $cotizacion,
        Collection $partidas,
        string $campo
    ): float {
        $sufijo = $cotizacion->moneda === 'USD' ? 'dolares' : 'soles';

        return round((float) $partidas->sum($campo . '_' . $sufijo), 4);
    }
}
