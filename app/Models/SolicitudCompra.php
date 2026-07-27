<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudCompra extends Model
{
    use HasFactory;

    protected $table = 'solicitudes_compra';

    protected $fillable = [
        'cotizacion_id',
        'codigo',
        'fecha_solicitud',
        'descripcion',
        'estado',
        'solicitado_por',
        'aprobado_por',
        'aprobado_en',
        'rechazado_por',
        'rechazado_en',
        'motivo_rechazo',
        'anulado_por',
        'anulado_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'date',
            'aprobado_en' => 'datetime',
            'rechazado_en' => 'datetime',
            'anulado_en' => 'datetime',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function rechazador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazado_por');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(SolicitudCompraDetalle::class);
    }

    public function ordenCompra(): HasOne
    {
        return $this->hasOne(OrdenCompra::class);
    }

    public function estaAprobada(): bool
    {
        return $this->estado === 'APROBADA';
    }

    public function estaConvertida(): bool
    {
        return $this->estado === 'CONVERTIDA';
    }
}
