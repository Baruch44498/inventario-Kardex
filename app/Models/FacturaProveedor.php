<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacturaProveedor extends Model
{
    use HasFactory;

    protected $table = 'facturas_proveedor';

    protected $fillable = [
        'orden_compra_id',
        'proveedor_id',
        'tipo_documento',
        'serie',
        'numero',
        'fecha_emision',
        'fecha_vencimiento',
        'moneda',
        'tipo_cambio',
        'subtotal',
        'impuesto',
        'total',
        'ajuste_redondeo',
        'observacion',
        'archivo_original_path',
        'archivo_original_nombre',
        'archivo_original_mime',
        'archivo_original_hash',
        'estado',
        'registrado_por',
        'anulado_por',
        'anulado_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
            'tipo_cambio' => 'decimal:6',
            'subtotal' => 'decimal:4',
            'impuesto' => 'decimal:4',
            'total' => 'decimal:4',
            'ajuste_redondeo' => 'decimal:4',
            'anulado_en' => 'datetime',
        ];
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(FacturaProveedorDetalle::class);
    }

    public function estaPagada(): bool
    {
        return $this->estado === 'PAGADA';
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'ANULADA';
    }

    public function numeroVisible(): string
    {
        return trim("{$this->serie}-{$this->numero}", '-');
    }

    public function simboloMoneda(): string
    {
        return $this->moneda === 'USD' ? 'US$' : 'S/';
    }

    public function factorSoles(): float
    {
        return $this->moneda === 'USD' ? (float) $this->tipo_cambio : 1.0;
    }

    public function totalEnSoles(): float
    {
        return round((float) $this->total * $this->factorSoles(), 4);
    }

    public function subtotalEnSoles(): float
    {
        return round((float) $this->subtotal * $this->factorSoles(), 4);
    }

    public function impuestoEnSoles(): float
    {
        return round((float) $this->impuesto * $this->factorSoles(), 4);
    }

    public function creditoFiscalEnSoles(): float
    {
        return $this->tipo_documento === 'FACTURA'
            ? $this->impuestoEnSoles()
            : 0.0;
    }

    public function permiteCreditoFiscal(): bool
    {
        return $this->tipo_documento === 'FACTURA' && ! $this->estaAnulada();
    }

    public function tieneArchivoOriginal(): bool
    {
        return filled($this->archivo_original_path);
    }

    public function estadoVisible(): string
    {
        return match ($this->estado) {
            'REGISTRADA' => 'Registrada',
            'PAGADA' => 'Pagada',
            'ANULADA' => 'Anulada',
            default => str($this->estado)->replace('_', ' ')->title()->toString(),
        };
    }

    public function estadoClase(): string
    {
        return match ($this->estado) {
            'REGISTRADA' => 'info',
            'PAGADA' => 'success',
            'ANULADA' => 'danger',
            default => 'neutral',
        };
    }

    public function notasIngreso(): HasMany
    {
        return $this->hasMany(NotaIngreso::class);
    }
}
