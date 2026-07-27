<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cotizacion extends Model
{
    use HasFactory;
    protected $table = 'cotizaciones';

    protected $fillable = [
        'requisicion_id',
        'proveedor_id',
        'codigo',
        'numero_documento',
        'fecha_cotizacion',
        'fecha_validez',
        'moneda',
        'tipo_cambio',
        'subtotal',
        'impuesto',
        'total',
        'condiciones_pago',
        'condiciones_entrega',
        'observacion',
        'estado',
        'registrado_por',
        'evaluado_por',
        'evaluado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cotizacion' => 'date',
            'fecha_validez' => 'date',
            'tipo_cambio' => 'decimal:6',
            'subtotal' => 'decimal:4',
            'impuesto' => 'decimal:4',
            'total' => 'decimal:4',
            'evaluado_en' => 'datetime',
        ];
    }

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CotizacionDetalle::class);
    }

    public function estaSeleccionada(): bool
    {
        return $this->estado === 'SELECCIONADA';
    }
    public function solicitudCompra(): HasOne
    {
        return $this->hasOne(SolicitudCompra::class);
    }
}
