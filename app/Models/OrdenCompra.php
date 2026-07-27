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
    public function notasIngreso(): HasMany
    {
        return $this->hasMany(NotaIngreso::class);
    }
}
