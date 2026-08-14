<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenCompraDetalle extends Model
{
    use HasFactory;

    protected $table = 'orden_compra_detalles';

    protected $fillable = [
        'orden_compra_id',
        'solicitud_compra_detalle_id',
        'producto_id',
        'cantidad_ordenada',
        'cantidad_recibida',
        'precio_unitario',
        'descuento_porcentaje',
        'subtotal',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_ordenada' => 'decimal:3',
            'cantidad_recibida' => 'decimal:3',
            'precio_unitario' => 'decimal:4',
            'descuento_porcentaje' => 'decimal:4',
            'subtotal' => 'decimal:4',
        ];
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class);
    }

    public function solicitudCompraDetalle(): BelongsTo
    {
        return $this->belongsTo(SolicitudCompraDetalle::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function facturaProveedorDetalles(): HasMany
    {
        return $this->hasMany(FacturaProveedorDetalle::class);
    }

    public function estaCompletamenteRecibido(): bool
    {
        return (float) $this->cantidad_recibida >= (float) $this->cantidad_ordenada;
    }

    public function cantidadPendiente(): float
    {
        return max(0, round(
            (float) $this->cantidad_ordenada - (float) $this->cantidad_recibida,
            3
        ));
    }

    public function porcentajeRecibido(): float
    {
        $ordenada = (float) $this->cantidad_ordenada;

        return $ordenada > 0
            ? min(100, round(((float) $this->cantidad_recibida / $ordenada) * 100, 2))
            : 0;
    }

    /**
     * Devuelve el costo operativo de inventario con IGV incluido y expresado en soles.
     *
     * La cotización conserva base e IGV separados para Contabilidad, pero Almacén
     * valoriza la entrada por el importe total pagado. Si la línea no conserva la
     * relación con la cotización, el total de la OC se distribuye proporcionalmente
     * entre sus líneas para incluir también cualquier ajuste documental.
     */
    public function costoUnitarioInventarioSoles(): float
    {
        $orden = $this->ordenCompra;
        $cotizacionDetalle = $this->solicitudCompraDetalle?->cotizacionDetalle;
        $costoDocumento = 0.0;

        if ($cotizacionDetalle) {
            $costoDocumento = $cotizacionDetalle->precioFinalUnitario();
        } elseif ($orden) {
            $totalLineas = (float) $orden->detalles()->sum('subtotal');
            $cantidad = (float) $this->cantidad_ordenada;
            $factorTotalDocumento = $totalLineas > 0
                ? (float) $orden->total / $totalLineas
                : 1.0;
            $costoDocumento = $cantidad > 0
                ? ((float) $this->subtotal / $cantidad) * $factorTotalDocumento
                : 0.0;
        }

        $factorMoneda = $orden?->moneda === 'USD'
            ? (float) $orden->tipo_cambio
            : 1.0;

        return round($costoDocumento * $factorMoneda, 4);
    }

    public function notaIngresoDetalles(): HasMany
    {
        return $this->hasMany(NotaIngresoDetalle::class);
    }
}
