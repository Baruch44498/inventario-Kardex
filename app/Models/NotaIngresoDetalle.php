<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaIngresoDetalle extends Model
{
    use HasFactory;

    protected $table = 'nota_ingreso_detalles';

    protected $fillable = [
        'nota_ingreso_id',
        'orden_compra_detalle_id',
        'nota_salida_detalle_id',
        'proforma_detalle_id',
        'producto_id',
        'repisa_id',
        'cantidad',
        'costo_unitario',
        'subtotal',
        'lote',
        'fecha_vencimiento',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'costo_unitario' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'fecha_vencimiento' => 'date',
        ];
    }

    public function notaIngreso(): BelongsTo
    {
        return $this->belongsTo(NotaIngreso::class);
    }

    public function notaSalidaDetalle(): BelongsTo
    {
        return $this->belongsTo(NotaSalidaDetalle::class);
    }

    public function proformaDetalle(): BelongsTo
    {
        return $this->belongsTo(ProformaDetalle::class);
    }

    public function ordenCompraDetalle(): BelongsTo
    {
        return $this->belongsTo(OrdenCompraDetalle::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function repisa(): BelongsTo
    {
        return $this->belongsTo(Repisa::class);
    }
}
