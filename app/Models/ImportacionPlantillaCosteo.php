<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportacionPlantillaCosteo extends Model
{
    use HasFactory;

    protected $table = 'importaciones_plantilla_costeo';

    protected $fillable = [
        'tipo_orden_id', 'nombre', 'descripcion', 'hoja', 'nombre_original',
        'ruta_archivo', 'mime_type', 'advertencias', 'estado', 'creado_por',
        'confirmado_por', 'confirmado_en',
    ];

    protected function casts(): array
    {
        return [
            'advertencias' => 'array',
            'confirmado_en' => 'datetime',
        ];
    }

    public function tipoOrden(): BelongsTo
    {
        return $this->belongsTo(TipoOrden::class);
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(ImportacionPlantillaCosteoPartida::class, 'importacion_id')
            ->orderBy('orden_secuencia');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    public function esBorrador(): bool
    {
        return $this->estado === 'BORRADOR';
    }
}
