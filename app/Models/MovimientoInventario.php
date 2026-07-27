<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    use HasFactory;

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'inventario_id',
        'producto_id',
        'repisa_id',
        'tipo_movimiento',
        'motivo',
        'origen_tipo',
        'origen_id',
        'origen_detalle_id',
        'cantidad',
        'stock_anterior',
        'stock_posterior',
        'costo_unitario',
        'costo_promedio_anterior',
        'costo_promedio_nuevo',
        'fecha_movimiento',
        'observacion',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'stock_anterior' => 'decimal:3',
            'stock_posterior' => 'decimal:3',
            'costo_unitario' => 'decimal:4',
            'costo_promedio_anterior' => 'decimal:4',
            'costo_promedio_nuevo' => 'decimal:4',
            'fecha_movimiento' => 'datetime',
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

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function esEntrada(): bool
    {
        return $this->tipo_movimiento === 'ENTRADA';
    }
}
