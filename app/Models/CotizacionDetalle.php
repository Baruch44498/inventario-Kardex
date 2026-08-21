<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CotizacionDetalle extends Model
{
    use HasFactory;

    protected $table = 'cotizacion_detalles';

    protected $fillable = [
        'cotizacion_id',
        'requisicion_detalle_id',
        'tipo_vinculacion',
        'vinculacion_origen',
        'codigo_documento',
        'descripcion_documento',
        'producto_id',
        'producto_presentacion_id',
        'presentacion_nombre',
        'cantidad_presentacion',
        'factor_conversion',
        'precio_presentacion',
        'cantidad',
        'precio_unitario',
        'descuento_porcentaje',
        'descuento_modo',
        'descuento_tipo',
        'descuento_valor',
        'igv_modo',
        'igv_porcentaje',
        'subtotal',
        'impuesto',
        'total',
        'marca_ofertada',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'cantidad_presentacion' => 'decimal:3',
            'factor_conversion' => 'decimal:3',
            'precio_presentacion' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'descuento_porcentaje' => 'decimal:4',
            'descuento_valor' => 'decimal:4',
            'igv_porcentaje' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'impuesto' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function requisicionDetalle(): BelongsTo
    {
        return $this->belongsTo(RequisicionDetalle::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(ProductoPresentacion::class, 'producto_presentacion_id');
    }

    public function tipoVinculacionEfectivo(): string
    {
        if ($this->tipo_vinculacion) {
            return $this->tipo_vinculacion;
        }

        if (! $this->requisicion_detalle_id) {
            return 'ADICIONAL';
        }

        return (int) $this->requisicionDetalle?->producto_id === (int) $this->producto_id
            ? 'SOLICITADO'
            : 'ALTERNATIVA';
    }

    public function vinculacionVisible(): string
    {
        return match ($this->tipoVinculacionEfectivo()) {
            'SOLICITADO' => 'Producto solicitado',
            'ALTERNATIVA' => 'Alternativa ofrecida',
            default => 'Producto adicional',
        };
    }

    public function solicitudCompraDetalles(): HasMany
    {
        return $this->hasMany(SolicitudCompraDetalle::class);
    }

    public function precioNetoUnitario(): float
    {
        $precio = (float) $this->precio_unitario;

        if ($this->descuento_modo === 'APLICAR') {
            $valor = (float) $this->descuento_valor;

            return match ($this->descuento_tipo) {
                'PORCENTAJE' => round($precio * (1 - ($valor / 100)), 4),
                'MONTO' => round(max(0, $precio - $valor), 4),
                default => $precio,
            };
        }

        if ($this->descuento_modo !== null) {
            return $precio;
        }

        // Compatibilidad con instancias y registros anteriores al bloque 16.0.3.1.
        $descuento = (float) $this->descuento_porcentaje / 100;

        return round($precio * (1 - $descuento), 4);
    }

    public function precioBaseUnitario(): float
    {
        $cantidad = (float) $this->cantidad;

        if ($cantidad > 0 && $this->subtotal !== null) {
            return round((float) $this->subtotal / $cantidad, 4);
        }

        $neto = $this->precioNetoUnitario();

        return $this->igv_modo === 'INCLUIDO'
            ? round($neto / 1.18, 4)
            : $neto;
    }

    public function precioFinalUnitario(): float
    {
        $cantidad = (float) $this->cantidad;

        if ($cantidad > 0 && $this->total !== null) {
            $precioFinal = (float) $this->total / $cantidad;

            return round(
                $precioFinal * $this->factorDespuesDescuentoGlobal(),
                4
            );
        }

        $neto = $this->precioNetoUnitario();

        $precioFinal = $this->igv_modo === 'AGREGAR'
            ? round($neto * 1.18, 4)
            : $neto;

        return round(
            $precioFinal * $this->factorDespuesDescuentoGlobal(),
            4
        );
    }

    public function precioEquivalentePen(): ?float
    {
        if (! $this->cotizacion) {
            return null;
        }

        if ($this->cotizacion->moneda === 'PEN') {
            return $this->precioFinalUnitario();
        }

        if (
            $this->cotizacion->moneda === 'USD'
            && (float) $this->cotizacion->tipo_cambio > 0
        ) {
            return round(
                $this->precioFinalUnitario() * (float) $this->cotizacion->tipo_cambio,
                4
            );
        }

        return null;
    }

    public function descuentoVisible(): string
    {
        if ($this->descuento_modo === 'INCLUIDO') {
            return 'Precio final informado';
        }

        if ($this->descuento_modo !== 'APLICAR') {
            return 'Sin descuento';
        }

        return $this->referenciaDescuento('Aplicado');
    }

    public function igvVisible(): string
    {
        return match ($this->igv_modo) {
            'INCLUIDO' => 'IGV 18 % incluido',
            'AGREGAR' => 'IGV 18 % agregado',
            'NO_APLICA' => 'Sin IGV',
            default => (float) $this->impuesto > 0 ? 'Con IGV' : 'Sin IGV',
        };
    }

    private function referenciaDescuento(string $prefijo): string
    {
        if ($this->descuento_tipo === 'PORCENTAJE') {
            return sprintf('%s (%.2f %%)', $prefijo, (float) $this->descuento_valor);
        }

        if ($this->descuento_tipo === 'MONTO') {
            $factor = (float) ($this->factor_conversion ?? 1);
            $factor = $factor > 0 ? $factor : 1.0;
            $valor = (float) $this->descuento_valor * $factor;
            $unidad = $factor > 1
                ? ($this->presentacion_nombre ?: 'presentación')
                : 'unidad';

            return sprintf(
                '%s (%s %.2f por %s)',
                $prefijo,
                $this->cotizacion?->simboloMoneda() ?? '',
                $valor,
                $unidad
            );
        }

        return $prefijo;
    }

    private function factorDespuesDescuentoGlobal(): float
    {
        if (! $this->cotizacion) {
            return 1.0;
        }

        $subtotal = (float) $this->cotizacion->subtotal;
        $descuento = (float) $this->cotizacion->descuento_global_monto;

        if ($subtotal <= 0 || $descuento <= 0) {
            return 1.0;
        }

        return max(0.0, 1 - ($descuento / $subtotal));
    }
}
