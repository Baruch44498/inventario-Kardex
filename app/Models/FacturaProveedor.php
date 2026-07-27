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
        'observacion',
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
    public function notasIngreso(): HasMany
    {
        return $this->hasMany(NotaIngreso::class);
    }
}
