<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionDetalle extends Model
{
    use HasFactory;

    protected $table = 'cotizacion_detalles';

    protected $fillable = [
        'cotizacion_id',
        'requisicion_detalle_id',
        'producto_id',
        'cantidad',
        'precio_unitario',
        'descuento_porcentaje',
        'subtotal',
        'marca_ofertada',
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
    public function solicitudCompraDetalles(): HasMany
    {
        return $this->hasMany(SolicitudCompraDetalle::class);
    }
}
