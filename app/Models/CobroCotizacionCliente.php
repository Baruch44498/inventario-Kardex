<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobroCotizacionCliente extends Model
{
    protected $table = 'cobros_cotizacion_cliente';

    protected $fillable = [
        'fecha_cobro', 'monto', 'medio_cobro', 'referencia', 'observacion',
        'registrado_por', 'anulado_por', 'anulado_en', 'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cobro' => 'date',
            'monto' => 'decimal:4',
            'anulado_en' => 'datetime',
        ];
    }

    public function cotizacionCliente(): BelongsTo
    {
        return $this->belongsTo(CotizacionCliente::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_en !== null;
    }
}
