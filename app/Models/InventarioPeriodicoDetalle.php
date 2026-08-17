<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioPeriodicoDetalle extends Model
{
    use HasFactory;

    protected $table = 'inventario_periodico_detalles';

    protected $fillable = [
        'inventario_periodico_id',
        'inventario_id',
        'producto_id',
        'repisa_id',
        'stock_sistema',
        'stock_contado',
        'diferencia',
        'costo_promedio_soles',
        'valor_sistema_soles',
        'valor_diferencia_soles',
        'movimiento_id_corte',
        'observacion',
        'contado_por',
        'contado_en',
    ];

    protected function casts(): array
    {
        return [
            'stock_sistema' => 'decimal:3',
            'stock_contado' => 'decimal:3',
            'diferencia' => 'decimal:3',
            'costo_promedio_soles' => 'decimal:4',
            'valor_sistema_soles' => 'decimal:4',
            'valor_diferencia_soles' => 'decimal:4',
            'contado_en' => 'datetime',
        ];
    }

    public function inventarioPeriodico(): BelongsTo
    {
        return $this->belongsTo(InventarioPeriodico::class);
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

    public function contadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contado_por');
    }
}
