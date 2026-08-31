<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialPlanificadoOrdenArea extends Model
{
    use HasFactory;

    protected $table = 'materiales_planificados_orden_area';

    protected $fillable = [
        'orden_operacion_id', 'orden_area_id', 'producto_id', 'codigo_producto',
        'descripcion_producto', 'unidad', 'cantidad_estimada',
        'costo_unitario_estimado_soles', 'costo_total_estimado_soles', 'congelado_en',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_estimada' => 'decimal:3',
            'costo_unitario_estimado_soles' => 'decimal:4',
            'costo_total_estimado_soles' => 'decimal:4',
            'congelado_en' => 'datetime',
        ];
    }

    public function ordenOperacion(): BelongsTo
    {
        return $this->belongsTo(OrdenOperacion::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(OrdenArea::class, 'orden_area_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
