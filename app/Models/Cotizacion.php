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
        'total_calculado',
        'ajuste_redondeo',
        'moneda_documento',
        'subtotal_documento',
        'impuesto_documento',
        'total_documento',
        'ajuste_redondeo_motivo',
        'ajuste_redondeo_confirmado_por',
        'ajuste_redondeo_confirmado_en',
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
        'motivo_evaluacion',
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
            'total_calculado' => 'decimal:4',
            'ajuste_redondeo' => 'decimal:4',
            'subtotal_documento' => 'decimal:4',
            'impuesto_documento' => 'decimal:4',
            'total_documento' => 'decimal:4',
            'ajuste_redondeo_confirmado_en' => 'datetime',
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

    public function confirmadorAjusteRedondeo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ajuste_redondeo_confirmado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CotizacionDetalle::class);
    }

    public function solicitudCompra(): HasOne
    {
        return $this->hasOne(SolicitudCompra::class);
    }

    public function importacionAsistida(): HasOne
    {
        return $this->hasOne(ImportacionCotizacionProveedor::class, 'cotizacion_id');
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', '!=', 'ANULADA');
    }

    public function scopeDisponiblesParaCompra(Builder $query): Builder
    {
        return $query
            ->where('estado', 'REGISTRADA')
            ->whereDoesntHave('solicitudCompra');
    }

    public function estaSeleccionada(): bool
    {
        return $this->estado === 'SELECCIONADA';
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'ANULADA';
    }

    public function estaArchivada(): bool
    {
        return in_array($this->estado, ['NO_REQUERIDA', 'NO_UTILIZADA'], true);
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
        return $this->estado === 'REGISTRADA' && ! $this->estaUtilizada();
    }

    public function puedeClasificar(): bool
    {
        return $this->estado === 'REGISTRADA' && ! $this->estaUtilizada();
    }

    public function puedeEnviarAContabilidad(): bool
    {
        return $this->estado === 'REGISTRADA' && ! $this->estaUtilizada();
    }

    public function puedeInvalidar(): bool
    {
        return ! $this->estaAnulada() && ! $this->estaUtilizada();
    }

    public function estadoVisible(): string
    {
        return match ($this->estado) {
            'SELECCIONADA' => 'Enviada a Contabilidad',
            'NO_REQUERIDA' => 'No requerida',
            'NO_UTILIZADA' => 'No utilizada',
            'ANULADA' => 'Invalidada',
            default => 'Pendiente de decisión',
        };
    }

    public function estadoClase(): string
    {
        return match ($this->estado) {
            'SELECCIONADA' => 'success',
            'NO_REQUERIDA', 'NO_UTILIZADA' => 'neutral',
            'ANULADA' => 'danger',
            default => 'info',
        };
    }

    public function simboloMoneda(): string
    {
        return $this->moneda === 'USD' ? 'US$' : 'S/';
    }

    public function incluyeIgv(): bool
    {
        return (float) $this->impuesto > 0;
    }

    public function tieneAjusteRedondeo(): bool
    {
        return abs((float) $this->ajuste_redondeo) >= 0.005;
    }

    public function totalCalculadoVisible(): float
    {
        return round(
            $this->total_calculado !== null
                ? (float) $this->total_calculado
                : (float) $this->total,
            2
        );
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
