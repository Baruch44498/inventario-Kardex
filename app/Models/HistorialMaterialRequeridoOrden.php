<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialMaterialRequeridoOrden extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'historial_materiales_requeridos_orden';

    protected $fillable = [
        'material_requerido_orden_id',
        'orden_operacion_id',
        'producto_id',
        'tipo_movimiento',
        'cantidad_anterior',
        'cantidad_cambio',
        'cantidad_nueva',
        'motivo',
        'registrado_por',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_anterior' => 'decimal:3',
            'cantidad_cambio' => 'decimal:3',
            'cantidad_nueva' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    public function materialRequerido(): BelongsTo
    {
        return $this->belongsTo(MaterialRequeridoOrden::class);
    }

    public function ordenOperacion(): BelongsTo
    {
        return $this->belongsTo(OrdenOperacion::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function tipoVisible(): string
    {
        return match ($this->tipo_movimiento) {
            'INICIAL' => 'Requerimiento inicial',
            'ADICIONAL' => 'Material adicional',
            'AJUSTE_AUMENTO' => 'Aumento de requerimiento',
            'AJUSTE_REDUCCION' => 'Reducción de requerimiento',
            'CONGELACION' => 'Previsión congelada al activar',
            default => 'Modificación',
        };
    }
}
