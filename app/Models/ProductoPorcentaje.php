<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoPorcentaje extends Model
{
    use HasFactory;

    protected $fillable = [
        'producto_id',
        'concepto',
        'valor_porcentaje',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'valor_porcentaje' => 'decimal:4',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'estado' => 'boolean',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
