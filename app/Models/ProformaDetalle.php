<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaDetalle extends Model
{
    use HasFactory;

    protected $fillable = [
        'proforma_id',
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

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function precioFueAjustado(): bool
    {
        return $this->precio_sugerido !== null
            && abs(
                (float) $this->precio_unitario
                    - (float) $this->precio_sugerido
            ) > 0.0001;
    }
}
