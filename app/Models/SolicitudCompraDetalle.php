<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitudCompraDetalle extends Model
{
    use HasFactory;

    protected $table = 'solicitud_compra_detalles';

    protected $fillable = [
        'solicitud_compra_id',
        'cotizacion_detalle_id',
        'producto_id',
        'cantidad',
        'precio_unitario',
        'descuento_porcentaje',
        'subtotal',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'decimal:4',
            'descuento_porcentaje' => 'decimal:4',
            'subtotal' => 'decimal:4',
        ];
    }

    public function solicitudCompra(): BelongsTo
    {
        return $this->belongsTo(SolicitudCompra::class);
    }

    public function cotizacionDetalle(): BelongsTo
    {
        return $this->belongsTo(CotizacionDetalle::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function ordenCompraDetalles(): HasMany
    {
        return $this->hasMany(OrdenCompraDetalle::class);
    }
}
