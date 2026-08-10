<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialRequeridoOrden extends Model
{
    use HasFactory;

    protected $table = 'materiales_requeridos_orden';

    protected $fillable = [
        'orden_operacion_id',
        'producto_id',
        'cantidad_requerida',
        'cantidad_prevista',
        'observacion',
        'creado_por',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_requerida' => 'decimal:3',
            'cantidad_prevista' => 'decimal:3',
        ];
    }

    public function ordenOperacion(): BelongsTo
    {
        return $this->belongsTo(OrdenOperacion::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialMaterialRequeridoOrden::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function cantidadInicial(): float
    {
        // Antes de activar la orden, la planificación todavía puede corregirse y
        // no debe contabilizarse como desviación. Al activar se congela este valor.
        if ($this->cantidad_prevista !== null) {
            return round((float) $this->cantidad_prevista, 3);
        }

        return round((float) $this->cantidad_requerida, 3);
    }

    public function previsionCongelada(): bool
    {
        return $this->cantidad_prevista !== null;
    }

    public function variacionAcumulada(): float
    {
        return round((float) $this->cantidad_requerida - $this->cantidadInicial(), 3);
    }
}
