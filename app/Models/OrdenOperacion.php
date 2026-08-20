<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrdenOperacion extends Model
{
    use HasFactory;

    protected $table = 'ordenes_operacion';

    protected $fillable = [
        'tipo_orden_id',
        'cotizacion_cliente_id',
        'cliente_id',
        'cliente_direccion_id',
        'vehiculo_id',
        'codigo_orden',
        'numero_correlativo',
        'anio',
        'fecha_apertura',
        'descripcion',
        'estado',
        'iniciado_en',
        'iniciado_por',
        'creado_por',
        'cerrado_en',
        'cerrado_por',
        'observacion_cierre',
        'ingreso_neto_cierre_soles',
        'costo_real_cierre_soles',
        'utilidad_real_cierre_soles',
        'margen_real_cierre_porcentaje',
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
            'iniciado_en' => 'datetime',
            'cerrado_en' => 'datetime',
            'ingreso_neto_cierre_soles' => 'decimal:4',
            'costo_real_cierre_soles' => 'decimal:4',
            'utilidad_real_cierre_soles' => 'decimal:4',
            'margen_real_cierre_porcentaje' => 'decimal:4',
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

    public function iniciador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciado_por');
    }

    public function cerrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
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

    public function cotizacionOrigen(): BelongsTo
    {
        return $this->belongsTo(CotizacionCliente::class, 'cotizacion_cliente_id');
    }

    public function cotizacionVinculada(): ?CotizacionCliente
    {
        return $this->cotizacionOrigen ?: $this->cotizacionCliente;
    }

    public function cotizacionComponente(): HasOne
    {
        return $this->hasOne(CotizacionComponente::class, 'orden_operacion_id');
    }

    public function detallesCotizados(): HasManyThrough
    {
        return $this->hasManyThrough(
            CotizacionClienteDetalle::class,
            CotizacionComponente::class,
            'orden_operacion_id',
            'componente_id',
            'id',
            'id'
        );
    }

    public function avances(): HasMany
    {
        return $this->hasMany(AvanceOrdenOperacion::class)
            ->latest('registrado_en')
            ->latest('id');
    }

    public function ultimoAvance(): HasOne
    {
        return $this->hasOne(AvanceOrdenOperacion::class)->latestOfMany();
    }

    public function costosDirectos(): HasMany
    {
        return $this->hasMany(CostoDirectoOrden::class)
            ->latest('fecha_costo')
            ->latest('id');
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
