<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenCompra extends Model
{
    use HasFactory;

    protected $table = 'ordenes_compra';

    protected $fillable = [
        'solicitud_compra_id',
        'proveedor_id',
        'codigo',
        'numero_documento_proveedor',
        'fecha_emision',
        'fecha_entrega_requerida',
        'moneda',
        'tipo_cambio',
        'subtotal',
        'impuesto',
        'ajuste_redondeo',
        'total',
        'condiciones_pago',
        'condiciones_entrega',
        'observacion',
        'estado',
        'emitido_por',
        'aprobado_por',
        'aprobado_en',
        'anulado_por',
        'anulado_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_entrega_requerida' => 'date',
            'tipo_cambio' => 'decimal:6',
            'subtotal' => 'decimal:4',
            'impuesto' => 'decimal:4',
            'ajuste_redondeo' => 'decimal:4',
            'total' => 'decimal:4',
            'aprobado_en' => 'datetime',
            'anulado_en' => 'datetime',
        ];
    }

    public function solicitudCompra(): BelongsTo
    {
        return $this->belongsTo(SolicitudCompra::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
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
        return $this->hasMany(OrdenCompraDetalle::class);
    }

    public function facturasProveedor(): HasMany
    {
        return $this->hasMany(FacturaProveedor::class);
    }

    public function estaAprobada(): bool
    {
        return $this->estado === 'APROBADA';
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'ANULADA';
    }

    public function estaRecibida(): bool
    {
        return $this->estado === 'RECIBIDA';
    }

    public function permiteRecepcion(): bool
    {
        return in_array($this->estado, ['APROBADA', 'PARCIALMENTE_RECIBIDA'], true);
    }

    public function puedeAnularse(): bool
    {
        return in_array($this->estado, ['APROBADA'], true)
            && ! $this->notasIngreso()->exists()
            && ! $this->facturasProveedor()->exists();
    }

    public function tieneAjusteRedondeo(): bool
    {
        return abs((float) $this->ajuste_redondeo) >= 0.005;
    }

    public function estadoVisible(): string
    {
        return match ($this->estado) {
            'APROBADA' => 'Aprobada para recepción',
            'PARCIALMENTE_RECIBIDA' => 'Recepción parcial',
            'RECIBIDA' => 'Recibida completamente',
            'ANULADA' => 'Anulada',
            default => str($this->estado)->replace('_', ' ')->title()->toString(),
        };
    }

    public function estadoClase(): string
    {
        return match ($this->estado) {
            'APROBADA' => 'info',
            'PARCIALMENTE_RECIBIDA' => 'warning',
            'RECIBIDA' => 'success',
            'ANULADA' => 'danger',
            default => 'neutral',
        };
    }

    public function notasIngreso(): HasMany
    {
        return $this->hasMany(NotaIngreso::class);
    }

    public function totalEnSoles(): float
    {
        $factor = $this->moneda === 'USD' ? (float) $this->tipo_cambio : 1.0;

        return round((float) $this->total * $factor, 4);
    }

    public function totalFacturadoSoles(): float
    {
        return round((float) $this->facturasProveedor()
            ->where('estado', '!=', 'ANULADA')
            ->get()
            ->sum(fn(FacturaProveedor $factura): float => $factura->totalEnSoles()), 4);
    }

    public function totalFacturadoDocumento(): float
    {
        return round((float) $this->facturasProveedor()
            ->where('estado', '!=', 'ANULADA')
            ->sum('total'), 4);
    }

    /** @return array{estado: string, etiqueta: string, clase: string, diferencia: float} */
    public function conciliacionFacturas(): array
    {
        // La OC y sus facturas usan la misma moneda. La conciliación documental
        // no debe cambiar por una variación del tipo de cambio entre fechas.
        $autorizado = (float) $this->total;
        $facturado = $this->totalFacturadoDocumento();
        $diferencia = round($facturado - $autorizado, 4);

        if ($facturado <= 0.0001) {
            return ['estado' => 'SIN_FACTURA', 'etiqueta' => 'Pendiente de factura', 'clase' => 'warning', 'diferencia' => $diferencia];
        }
        if (abs($diferencia) <= 0.05) {
            return ['estado' => 'CONCILIADA', 'etiqueta' => 'Factura conciliada', 'clase' => 'success', 'diferencia' => $diferencia];
        }
        if ($diferencia < 0) {
            return ['estado' => 'PARCIAL', 'etiqueta' => 'Facturación parcial', 'clase' => 'info', 'diferencia' => $diferencia];
        }

        return ['estado' => 'EXCEDIDA', 'etiqueta' => 'Factura excede la OC', 'clase' => 'danger', 'diferencia' => $diferencia];
    }
}
