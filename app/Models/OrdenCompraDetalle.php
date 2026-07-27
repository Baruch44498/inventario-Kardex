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
    public function notaIngresoDetalles(): HasMany
    {
        return $this->hasMany(NotaIngresoDetalle::class);
    }
}
