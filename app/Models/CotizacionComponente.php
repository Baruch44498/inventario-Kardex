<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CotizacionComponente extends Model
{
    use HasFactory;

    protected $table = 'cotizacion_componentes';

    protected $fillable = [
        'cotizacion_cliente_id',
        'tipo_orden_id',
        'descripcion_componente',
        'cliente_direccion_id',
        'vehiculo_id',
        'tipo_cambio_comparacion',
        'orden_secuencia',
        'orden_operacion_id',
    ];

    protected function casts(): array
    {
        return [
            'orden_secuencia' => 'integer',
            'tipo_cambio_comparacion' => 'decimal:6',
        ];
    }

    public function cotizacionCliente(): BelongsTo
    {
        return $this->belongsTo(CotizacionCliente::class);
    }

    public function tipoOrden(): BelongsTo
    {
        return $this->belongsTo(TipoOrden::class);
    }

    public function clienteDireccion(): BelongsTo
    {
        return $this->belongsTo(ClienteDireccion::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function ordenOperacion(): BelongsTo
    {
        return $this->belongsTo(OrdenOperacion::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CotizacionClienteDetalle::class, 'componente_id');
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(CotizacionPresupuesto::class, 'componente_id');
    }

    public function nombreVisible(): string
    {
        return ($this->tipoOrden?->codigo ?: 'Componente').' '.$this->orden_secuencia;
    }
}
