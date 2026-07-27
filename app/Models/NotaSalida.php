<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaSalida extends Model
{
    use HasFactory;

    protected $table = 'notas_salida';

    protected $fillable = [
        'orden_operacion_id',
        'codigo',
        'fecha_salida',
        'entregado_a',
        'observacion',
        'estado',
        'registrado_por',
        'confirmado_por',
        'confirmado_en',
        'anulado_por',
        'anulado_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_salida' => 'date',
            'confirmado_en' => 'datetime',
            'anulado_en' => 'datetime',
        ];
    }

    public function ordenOperacion(): BelongsTo
    {
        return $this->belongsTo(OrdenOperacion::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function confirmador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(NotaSalidaDetalle::class);
    }

    public function estaConfirmada(): bool
    {
        return $this->estado === 'CONFIRMADA';
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'ANULADA';
    }
}
