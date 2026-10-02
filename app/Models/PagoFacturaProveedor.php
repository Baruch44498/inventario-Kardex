<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoFacturaProveedor extends Model
{
    protected $table = 'pagos_factura_proveedor';

    protected $fillable = [
        'fecha_pago', 'monto', 'medio_pago', 'referencia', 'observacion',
        'registrado_por', 'anulado_por', 'anulado_en', 'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_pago' => 'date',
            'monto' => 'decimal:4',
            'anulado_en' => 'datetime',
        ];
    }

    public function facturaProveedor(): BelongsTo
    {
        return $this->belongsTo(FacturaProveedor::class);
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
