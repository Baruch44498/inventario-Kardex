<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioPeriodico extends Model
{
    use HasFactory;

    protected $table = 'inventarios_periodicos';

    protected $fillable = [
        'codigo',
        'repisa_id',
        'fecha_corte',
        'estado',
        'observacion',
        'total_lineas',
        'lineas_con_diferencia',
        'valor_sistema_soles',
        'valor_diferencia_soles',
        'abierto_por',
        'abierto_en',
        'cerrado_por',
        'cerrado_en',
        'anulado_por',
        'anulado_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_corte' => 'datetime',
            'abierto_en' => 'datetime',
            'cerrado_en' => 'datetime',
            'anulado_en' => 'datetime',
            'total_lineas' => 'integer',
            'lineas_con_diferencia' => 'integer',
            'valor_sistema_soles' => 'decimal:4',
            'valor_diferencia_soles' => 'decimal:4',
        ];
    }

    public function repisa(): BelongsTo
    {
        return $this->belongsTo(Repisa::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(InventarioPeriodicoDetalle::class);
    }

    public function abiertoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abierto_por');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function estaAbierto(): bool
    {
        return $this->estado === 'ABIERTO';
    }
}
