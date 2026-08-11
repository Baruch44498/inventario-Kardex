<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacionCotizacionProveedor extends Model
{
    use HasFactory;

    protected $table = 'importaciones_cotizacion_proveedor';

    protected $fillable = [
        'requisicion_id',
        'proveedor_id',
        'cotizacion_id',
        'tipo_archivo',
        'nombre_original',
        'ruta_archivo',
        'mime_type',
        'datos_extraidos',
        'advertencias',
        'estado',
        'creado_por',
        'confirmado_por',
        'confirmado_en',
    ];

    protected function casts(): array
    {
        return [
            'datos_extraidos' => 'array',
            'advertencias' => 'array',
            'confirmado_en' => 'datetime',
        ];
    }

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function confirmador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    public function esBorrador(): bool
    {
        return $this->estado === 'BORRADOR';
    }
}
