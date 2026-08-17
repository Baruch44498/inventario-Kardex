<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvanceOrdenOperacion extends Model
{
    use HasFactory;

    protected $table = 'avances_orden_operacion';

    protected $fillable = [
        'orden_operacion_id',
        'porcentaje',
        'detalle',
        'registrado_por',
        'registrado_en',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:2',
            'registrado_en' => 'datetime',
        ];
    }

    public function ordenOperacion(): BelongsTo
    {
        return $this->belongsTo(OrdenOperacion::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
