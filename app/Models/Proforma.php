<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proforma extends Model
{
    use HasFactory;

    public const ESTADOS = [
        'BORRADOR',
        'ENVIADA_A_LOGISTICA',
        'COTIZADA',
        'CONVERTIDA_EN_ORDEN',
        'ANULADA',
    ];

    public const TIPOS_ORIGEN = [
        'VENTA_DIRECTA',
    ];

    protected $fillable = [
        'cliente_id',
        'codigo',
        'tipo_origen',
        'fecha_emision',
        'fecha_validez',
        'moneda',
        'tipo_cambio',
        'margen_cliente_porcentaje',
        'subtotal',
        'impuesto',
        'total',
        'condiciones_pago',
        'condiciones_entrega',
        'observacion',
        'estado',
        'registrado_por',
        'emitido_por',
        'emitido_en',
        'enviado_por',
        'enviado_en',
        'anulado_por',
        'anulado_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_validez' => 'date',
            'tipo_cambio' => 'decimal:6',
            'margen_cliente_porcentaje' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'impuesto' => 'decimal:4',
            'total' => 'decimal:4',
            'emitido_en' => 'datetime',
            'enviado_en' => 'datetime',
            'anulado_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(ProformaDetalle::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    public function enviador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function cotizacionesCliente(): HasMany
    {
        return $this->hasMany(CotizacionCliente::class);
    }

    public function esEditable(): bool
    {
        return $this->estado === 'BORRADOR';
    }

    public function puedeEnviarse(): bool
    {
        return $this->estado === 'BORRADOR' && $this->detalles()->exists();
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'ANULADA';
    }

    public function origenVisible(): string
    {
        return 'Venta directa en Almacén';
    }

    public function simboloMoneda(): string
    {
        return $this->moneda === 'USD' ? 'US$' : 'S/';
    }
}
