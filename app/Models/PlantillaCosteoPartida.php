<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantillaCosteoPartida extends Model
{
    use HasFactory;

    protected $table = 'plantilla_costeo_partidas';

    protected $fillable = [
        'plantilla_costeo_id',
        'plantilla_area_id',
        'producto_id',
        'codigo_referencia',
        'tipo_costo',
        'ejecucion_servicio',
        'grupo_costo',
        'descripcion',
        'cantidad',
        'unidad',
        'moneda',
        'tipo_cambio',
        'costo_unitario',
        'margen_porcentaje',
        'carga_social_porcentaje',
        'igv_modo',
        'igv_porcentaje',
        'igv_venta_porcentaje',
        'observacion',
        'orden_secuencia',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'tipo_cambio' => 'decimal:6',
            'costo_unitario' => 'decimal:4',
            'margen_porcentaje' => 'decimal:4',
            'carga_social_porcentaje' => 'decimal:4',
            'igv_porcentaje' => 'decimal:4',
            'igv_venta_porcentaje' => 'decimal:4',
            'orden_secuencia' => 'integer',
        ];
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaCosteo::class, 'plantilla_costeo_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
