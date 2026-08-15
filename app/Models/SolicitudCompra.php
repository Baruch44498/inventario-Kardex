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
        'origen',
        'justificacion_origen',
        'total_lineas',
        'ajuste_redondeo',
        'total_seleccionado',
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
            'total_lineas' => 'decimal:4',
            'ajuste_redondeo' => 'decimal:4',
            'total_seleccionado' => 'decimal:4',
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

    public function estaPendiente(): bool
    {
        return $this->estado === 'PENDIENTE';
    }

    public function estaRechazada(): bool
    {
        return $this->estado === 'RECHAZADA';
    }

    public function puedeConvertirseEnOrden(): bool
    {
        return in_array($this->estado, ['PENDIENTE', 'APROBADA'], true)
            && ! $this->ordenCompra()->exists();
    }

    public function tieneAjusteRedondeo(): bool
    {
        return abs((float) $this->ajuste_redondeo) >= 0.005;
    }

    public function esCompraDirecta(): bool
    {
        return in_array($this->origen, ['COMPRA_DIRECTA', 'REGULARIZACION', 'URGENTE', 'REPOSICION'], true);
    }

    public function origenVisible(): string
    {
        return match ($this->origen) {
            'COMPRA_DIRECTA' => 'Compra directa',
            'REGULARIZACION' => 'Regularización',
            'URGENTE' => 'Compra urgente',
            'REPOSICION' => 'Reposición directa',
            default => 'Desde requerimiento',
        };
    }

    public function origenClase(): string
    {
        return match ($this->origen) {
            'REGULARIZACION' => 'danger',
            'URGENTE' => 'warning',
            'COMPRA_DIRECTA', 'REPOSICION' => 'info',
            default => 'neutral',
        };
    }

    public function estadoVisible(): string
    {
        return match ($this->estado) {
            'PENDIENTE' => 'Registro anterior pendiente de OC',
            'APROBADA' => 'Aprobada para compra',
            'RECHAZADA' => 'No utilizada (registro anterior)',
            'CONVERTIDA' => 'Convertida en orden',
            'ANULADA' => 'Anulada',
            default => str($this->estado)->replace('_', ' ')->title()->toString(),
        };
    }

    public function estadoClase(): string
    {
        return match ($this->estado) {
            'APROBADA', 'CONVERTIDA' => 'success',
            'RECHAZADA', 'ANULADA' => 'danger',
            default => 'warning',
        };
    }
}
