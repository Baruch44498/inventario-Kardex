<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cotizacion extends Model
{
    use HasFactory;

    protected $table = 'cotizaciones';

    protected $fillable = [
        'requisicion_id',
        'proveedor_id',
        'codigo',
        'numero_documento',
        'fecha_cotizacion',
        'fecha_validez',
        'moneda',
        'tipo_cambio',
        'descuento_global_modo',
        'descuento_global_tipo',
        'descuento_global_valor',
        'subtotal',
        'descuento_global_monto',
        'impuesto',
        'total',
        'condiciones_pago',
        'condiciones_entrega',
        'observacion',
        'estado',
        'origen_registro',
        'archivo_original_nombre',
        'archivo_original_path',
        'registrado_por',
        'evaluado_por',
        'evaluado_en',
        'anulado_por',
        'anulado_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cotizacion' => 'date',
            'fecha_validez' => 'date',
            'tipo_cambio' => 'decimal:6',
            'descuento_global_valor' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'descuento_global_monto' => 'decimal:4',
            'impuesto' => 'decimal:4',
            'total' => 'decimal:4',
            'evaluado_en' => 'datetime',
            'anulado_en' => 'datetime',
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

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluado_por');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CotizacionDetalle::class);
    }

    public function solicitudCompra(): HasOne
    {
        return $this->hasOne(SolicitudCompra::class);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', '!=', 'ANULADA');
    }

    public function estaSeleccionada(): bool
    {
        return $this->estado === 'SELECCIONADA';
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'ANULADA';
    }

    public function estaUtilizada(): bool
    {
        if ($this->estaSeleccionada()) {
            return true;
        }

        return $this->relationLoaded('solicitudCompra')
            ? $this->solicitudCompra !== null
            : $this->solicitudCompra()->exists();
    }

    public function puedeEditar(): bool
    {
        return ! $this->estaAnulada() && ! $this->estaUtilizada();
    }

    public function simboloMoneda(): string
    {
        return $this->moneda === 'USD' ? 'US$' : 'S/';
    }

    public function incluyeIgv(): bool
    {
        return (float) $this->impuesto > 0;
    }

    public function baseNeta(): float
    {
        return round(
            (float) $this->subtotal - (float) $this->descuento_global_monto,
            4
        );
    }

    public function descuentoGlobalVisible(): string
    {
        if ($this->descuento_global_modo === 'INCLUIDO') {
            return 'Precio final informado por el proveedor';
        }

        if ($this->descuento_global_modo !== 'APLICAR') {
            return 'Sin descuento general';
        }

        return $this->referenciaDescuentoGlobal('Aplicado');
    }

    private function referenciaDescuentoGlobal(string $prefijo): string
    {
        if ($this->descuento_global_tipo === 'PORCENTAJE') {
            return sprintf(
                '%s (%.2f %%)',
                $prefijo,
                (float) $this->descuento_global_valor
            );
        }

        if ($this->descuento_global_tipo === 'MONTO') {
            return sprintf(
                '%s (%s %.2f)',
                $prefijo,
                $this->simboloMoneda(),
                (float) $this->descuento_global_valor
            );
        }

        return $prefijo;
    }
}
