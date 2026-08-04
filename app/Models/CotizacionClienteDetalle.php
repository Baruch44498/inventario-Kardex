<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionClienteDetalle extends Model
{
    use HasFactory;

    protected $table = 'cotizacion_cliente_detalles';

    protected $fillable = [
        'cotizacion_cliente_id',
        'proforma_detalle_id',
        'producto_id',
        'codigo_producto',
        'descripcion',
        'unidad_medida',
        'cantidad',
        'costo_referencia',
        'margen_sugerido',
        'precio_sugerido',
        'precio_unitario',
        'igv_modo',
        'igv_porcentaje',
        'subtotal',
        'impuesto',
        'total',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'costo_referencia' => 'decimal:4',
            'margen_sugerido' => 'decimal:4',
            'precio_sugerido' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'igv_porcentaje' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'impuesto' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(CotizacionCliente::class, 'cotizacion_cliente_id');
    }

    public function proformaDetalle(): BelongsTo
    {
        return $this->belongsTo(ProformaDetalle::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function precioFueAjustado(): bool
    {
        return $this->precio_sugerido !== null
            && abs((float) $this->precio_unitario - (float) $this->precio_sugerido) > 0.0001;
    }
}
