<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequisicionDetalle extends Model
{
    use HasFactory;

    protected $table = 'requisicion_detalles';

    protected $fillable = [
        'requisicion_id',
        'producto_id',
        'cantidad_solicitada',
        'cantidad_atendida',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_solicitada' => 'decimal:3',
            'cantidad_atendida' => 'decimal:3',
        ];
    }

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function cotizacionDetalles(): HasMany
    {
        return $this->hasMany(CotizacionDetalle::class);
    }
}
