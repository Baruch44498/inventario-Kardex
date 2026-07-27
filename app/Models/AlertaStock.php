<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertaStock extends Model
{
    use HasFactory;

    protected $table = 'alertas_stock';

    protected $fillable = [
        'inventario_id',
        'producto_id',
        'repisa_id',
        'tipo_alerta',
        'nivel',
        'stock_actual',
        'stock_minimo',
        'mensaje',
        'estado',
        'detectada_en',
        'atendida_por',
        'atendida_en',
        'resuelta_por',
        'resuelta_en',
        'observacion_resolucion',
    ];

    protected function casts(): array
    {
        return [
            'stock_actual' => 'decimal:3',
            'stock_minimo' => 'decimal:3',
            'detectada_en' => 'datetime',
            'atendida_en' => 'datetime',
            'resuelta_en' => 'datetime',
        ];
    }

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function repisa(): BelongsTo
    {
        return $this->belongsTo(Repisa::class);
    }

    public function atendidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendida_por');
    }

    public function resueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }

    public function estaActiva(): bool
    {
        return in_array($this->estado, ['ACTIVA', 'ATENDIDA'], true);
    }

    public function esCritica(): bool
    {
        return $this->nivel === 'CRITICA';
    }
}
