<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventario extends Model
{
    use HasFactory;

    protected $fillable = [
        'producto_id',
        'repisa_id',
        'stock_actual',
        'stock_minimo',
        'stock_maximo',
        'costo_promedio_soles',
    ];

    protected function casts(): array
    {
        return [
            'stock_actual' => 'decimal:3',
            'stock_minimo' => 'decimal:3',
            'stock_maximo' => 'decimal:3',
            'costo_promedio_soles' => 'decimal:4',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function repisa(): BelongsTo
    {
        return $this->belongsTo(Repisa::class);
    }

    public function requiereReposicion(): bool
    {
        return (float) $this->stock_actual <= (float) $this->stock_minimo;
    }
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }
    public function alertasStock(): HasMany
    {
        return $this->hasMany(AlertaStock::class);
    }
}
