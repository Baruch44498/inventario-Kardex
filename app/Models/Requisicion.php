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
        'origen',
        'descripcion',
        'prioridad',
        'estado',
        'solicitado_por',
        'enviado_por',
        'enviado_en',
        'recibido_por',
        'recibido_en',
        'atendido_por',
        'atendido_en',
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
            'enviado_en' => 'datetime',
            'recibido_en' => 'datetime',
            'atendido_en' => 'datetime',
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


    public function enviador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    public function receptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por');
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

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialRequerimientoCompra::class, 'requisicion_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }


    public function esBorrador(): bool
    {
        return $this->estado === 'BORRADOR';
    }

    public function estaEnviada(): bool
    {
        return $this->estado === 'ENVIADA';
    }

    public function estaEnRevision(): bool
    {
        return $this->estado === 'EN_REVISION';
    }

    public function estaCotizando(): bool
    {
        return $this->estado === 'COTIZANDO';
    }

    public function estaAtendida(): bool
    {
        return $this->estado === 'ATENDIDA';
    }

    public function origenVisible(): string
    {
        return $this->origen === 'ORDEN_OPERACION'
            ? 'Orden de operación'
            : 'Reposición de stock';
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
