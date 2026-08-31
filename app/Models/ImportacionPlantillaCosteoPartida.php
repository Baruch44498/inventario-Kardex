<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacionPlantillaCosteoPartida extends Model
{
    use HasFactory;

    protected $table = 'importacion_plantilla_costeo_partidas';

    protected $fillable = [
        'importacion_id',
        'producto_id',
        'fila_excel',
        'grupo_costo',
        'codigo_referencia',
        'descripcion',
        'cantidad',
        'unidad_original',
        'tipo_costo',
        'ejecucion_servicio',
        'unidad',
        'moneda',
        'tipo_cambio',
        'costo_unitario',
        'margen_porcentaje',
        'carga_social_porcentaje',
        'igv_modo',
        'igv_porcentaje',
        'igv_venta_porcentaje',
        'estado_vinculacion',
        'omitida',
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
            'omitida' => 'boolean',
            'orden_secuencia' => 'integer',
        ];
    }

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(ImportacionPlantillaCosteo::class, 'importacion_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
