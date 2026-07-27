<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requisicion extends Model
{
    use HasFactory;
    protected $table = 'requisiciones';

    protected $fillable = [
        'orden_operacion_id',
        'codigo',
        'fecha_solicitud',
        'descripcion',
        'prioridad',
        'estado',
        'solicitado_por',
        'aprobado_por',
        'aprobado_en',
        'anulado_por',
        'anulado_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'date',
            'aprobado_en' => 'datetime',
            'anulado_en' => 'datetime',
        ];
    }

    public function ordenOperacion(): BelongsTo
    {
        return $this->belongsTo(OrdenOperacion::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RequisicionDetalle::class);
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }

    public function estaAprobada(): bool
    {
        return $this->estado === 'APROBADA';
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'ANULADA';
    }
}
