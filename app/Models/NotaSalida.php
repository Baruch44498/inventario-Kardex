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
        'orden_area_id',
        'area_trabajo',
        'motivo_salida',
        'proforma_id',
        'codigo',
        'fecha_salida',
        'entregado_a',
        'recibido_por_empleado_id',
        'recibido_por_nombre',
        'recibido_por_dni',
        'entregado_por_nombre',
        'entregado_por_dni',
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

    public function ordenArea(): BelongsTo
    {
        return $this->belongsTo(OrdenArea::class);
    }

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class);
    }

    public function recibidoPorEmpleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'recibido_por_empleado_id');
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

    public function motivoVisible(): string
    {
        return match ($this->motivo_salida) {
            'PROFORMA' => 'Proforma de Almacén',
            'USO_INTERNO' => 'Uso interno',
            'OTRO' => 'Otro',
            default => 'Orden de operación',
        };
    }
}
