<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservaMaterialOrden extends Model
{
    use HasFactory;

    protected $table = 'reservas_materiales_orden';

    protected $fillable = [
        'orden_operacion_id',
        'producto_id',
        'cantidad_reservada',
        'cantidad_atendida',
        'cantidad_liberada',
        'estado',
        'observacion',
        'reservado_por',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_reservada' => 'decimal:3',
            'cantidad_atendida' => 'decimal:3',
            'cantidad_liberada' => 'decimal:3',
        ];
    }

    public function ordenOperacion(): BelongsTo
    {
        return $this->belongsTo(OrdenOperacion::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function reservadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reservado_por');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function detallesSalida(): HasMany
    {
        return $this->hasMany(NotaSalidaDetalle::class, 'reserva_material_orden_id');
    }

    public function cantidadPendiente(): float
    {
        return max(0, round(
            (float) $this->cantidad_reservada
                - (float) $this->cantidad_atendida
                - (float) $this->cantidad_liberada,
            3
        ));
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'ACTIVA' && $this->cantidadPendiente() > 0.0001;
    }
}
