<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaSalidaDetalle extends Model
{
    use HasFactory;

    protected $table = 'nota_salida_detalles';

    protected $fillable = [
        'nota_salida_id',
        'proforma_detalle_id',
        'reserva_material_orden_id',
        'producto_id',
        'repisa_id',
        'cantidad',
        'cantidad_aplicada_reserva',
        'tratamiento',
        'costo_unitario_promedio',
        'subtotal',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'cantidad_aplicada_reserva' => 'decimal:3',
            'costo_unitario_promedio' => 'decimal:4',
            'subtotal' => 'decimal:4',
        ];
    }

    public function notaSalida(): BelongsTo
    {
        return $this->belongsTo(NotaSalida::class);
    }

    public function proformaDetalle(): BelongsTo
    {
        return $this->belongsTo(ProformaDetalle::class);
    }

    public function reservaMaterial(): BelongsTo
    {
        return $this->belongsTo(ReservaMaterialOrden::class, 'reserva_material_orden_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function repisa(): BelongsTo
    {
        return $this->belongsTo(Repisa::class);
    }

    public function tratamientoVisible(): string
    {
        return match ($this->tratamiento) {
            'USO_TEMPORAL' => 'Uso temporal / herramienta',
            'VENTA_DIRECTA' => 'Venta directa',
            'PRESTAMO_EXTERNO' => 'Préstamo externo',
            default => 'Consumo',
        };
    }
}
