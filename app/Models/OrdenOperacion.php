<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrdenOperacion extends Model
{
    use HasFactory;

    protected $table = 'ordenes_operacion';

    protected $fillable = [
        'tipo_orden_id',
        'cliente_id',
        'cliente_direccion_id',
        'vehiculo_id',
        'codigo_orden',
        'numero_correlativo',
        'anio',
        'fecha_apertura',
        'descripcion',
        'estado',
        'creado_por',
        'cerrado_en',
        'anulado_por',
        'anulado_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'numero_correlativo' => 'integer',
            'fecha_apertura' => 'date',
            'cerrado_en' => 'datetime',
            'anulado_en' => 'datetime',
        ];
    }

    public function tipoOrden(): BelongsTo
    {
        return $this->belongsTo(TipoOrden::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function clienteDireccion(): BelongsTo
    {
        return $this->belongsTo(ClienteDireccion::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function requisiciones(): HasMany
    {
        return $this->hasMany(Requisicion::class);
    }

    public function notasSalida(): HasMany
    {
        return $this->hasMany(NotaSalida::class);
    }

    public function reservasMateriales(): HasMany
    {
        return $this->hasMany(ReservaMaterialOrden::class);
    }

    public function materialesRequeridos(): HasMany
    {
        return $this->hasMany(MaterialRequeridoOrden::class);
    }

    public function cotizacionCliente(): HasOne
    {
        return $this->hasOne(CotizacionCliente::class);
    }

    public function estaAbierta(): bool
    {
        return $this->estado === 'ABIERTA';
    }

    public function estaEnProceso(): bool
    {
        return $this->estado === 'EN_PROCESO';
    }

    public function estaCerrada(): bool
    {
        return $this->estado === 'CERRADA';
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'ANULADA';
    }

    public function puedeEditar(): bool
    {
        return in_array($this->estado, ['ABIERTA', 'EN_PROCESO'], true);
    }
}
