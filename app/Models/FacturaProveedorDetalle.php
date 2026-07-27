<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaProveedorDetalle extends Model
{
    use HasFactory;

    protected $table = 'factura_proveedor_detalles';

    protected $fillable = [
        'factura_proveedor_id',
        'orden_compra_detalle_id',
        'producto_id',
        'descripcion',
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

    public function facturaProveedor(): BelongsTo
    {
        return $this->belongsTo(FacturaProveedor::class);
    }

    public function ordenCompraDetalle(): BelongsTo
    {
        return $this->belongsTo(OrdenCompraDetalle::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
