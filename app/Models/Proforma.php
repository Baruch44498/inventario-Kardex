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
        'SIN_COBRO',
        // Estado histórico conservado para documentos anteriores a 17.0.5.
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

    public function notasSalida(): HasMany
    {
        return $this->hasMany(NotaSalida::class);
    }

    public function notasIngreso(): HasMany
    {
        return $this->hasMany(NotaIngreso::class);
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

    public function tieneVentas(): bool
    {
        return $this->detalles()->where('tratamiento', 'VENTA')->exists();
    }

    public function tienePrestamos(): bool
    {
        return $this->detalles()->where('tratamiento', 'PRESTAMO')->exists();
    }

    public function prestamosPendientes(): bool
    {
        return $this->detalles()
            ->where('tratamiento', 'PRESTAMO')
            ->get()
            ->contains(fn(ProformaDetalle $detalle) => ! $detalle->prestamoRegularizado());
    }

    public function puedeConfirmarseSinCobro(): bool
    {
        return $this->estado === 'ENVIADA_A_LOGISTICA'
            && $this->detalles()->exists()
            && ! $this->tieneVentas();
    }

    public function origenVisible(): string
    {
        return 'Proforma de Almacén';
    }

    public function simboloMoneda(): string
    {
        return $this->moneda === 'USD' ? 'US$' : 'S/';
    }
}
