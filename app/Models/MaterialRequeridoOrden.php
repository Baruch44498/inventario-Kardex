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
        'observacion',
        'creado_por',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_requerida' => 'decimal:3',
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
        $inicial = $this->relationLoaded('historial')
            ? $this->historial->firstWhere('tipo_movimiento', 'INICIAL')
            : $this->historial()->where('tipo_movimiento', 'INICIAL')->first();

        return round((float) ($inicial?->cantidad_nueva ?? $this->cantidad_requerida), 3);
    }

    public function variacionAcumulada(): float
    {
        return round((float) $this->cantidad_requerida - $this->cantidadInicial(), 3);
    }
}
