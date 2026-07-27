<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    public $timestamps = false;


    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'estado',
        'ultimo_acceso_en',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'estado' => 'boolean',
            'ultimo_acceso_en' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function ordenesOperacionCreadas(): HasMany
    {
        return $this->hasMany(OrdenOperacion::class, 'creado_por');
    }

    public function ordenesOperacionAnuladas(): HasMany
    {
        return $this->hasMany(OrdenOperacion::class, 'anulado_por');
    }
    public function requisicionesSolicitadas(): HasMany
    {
        return $this->hasMany(Requisicion::class, 'solicitado_por');
    }

    public function requisicionesAprobadas(): HasMany
    {
        return $this->hasMany(Requisicion::class, 'aprobado_por');
    }

    public function requisicionesAnuladas(): HasMany
    {
        return $this->hasMany(Requisicion::class, 'anulado_por');
    }

    public function cotizacionesRegistradas(): HasMany
    {
        return $this->hasMany(Cotizacion::class, 'registrado_por');
    }

    public function cotizacionesEvaluadas(): HasMany
    {
        return $this->hasMany(Cotizacion::class, 'evaluado_por');
    }
    public function solicitudesCompraSolicitadas(): HasMany
    {
        return $this->hasMany(SolicitudCompra::class, 'solicitado_por');
    }

    public function solicitudesCompraAprobadas(): HasMany
    {
        return $this->hasMany(SolicitudCompra::class, 'aprobado_por');
    }

    public function solicitudesCompraRechazadas(): HasMany
    {
        return $this->hasMany(SolicitudCompra::class, 'rechazado_por');
    }

    public function solicitudesCompraAnuladas(): HasMany
    {
        return $this->hasMany(SolicitudCompra::class, 'anulado_por');
    }

    public function ordenesCompraEmitidas(): HasMany
    {
        return $this->hasMany(OrdenCompra::class, 'emitido_por');
    }

    public function ordenesCompraAprobadas(): HasMany
    {
        return $this->hasMany(OrdenCompra::class, 'aprobado_por');
    }

    public function ordenesCompraAnuladas(): HasMany
    {
        return $this->hasMany(OrdenCompra::class, 'anulado_por');
    }

    public function facturasProveedorRegistradas(): HasMany
    {
        return $this->hasMany(FacturaProveedor::class, 'registrado_por');
    }

    public function facturasProveedorAnuladas(): HasMany
    {
        return $this->hasMany(FacturaProveedor::class, 'anulado_por');
    }
    public function notasIngresoRegistradas(): HasMany
    {
        return $this->hasMany(NotaIngreso::class, 'registrado_por');
    }

    public function notasIngresoConfirmadas(): HasMany
    {
        return $this->hasMany(NotaIngreso::class, 'confirmado_por');
    }

    public function notasIngresoAnuladas(): HasMany
    {
        return $this->hasMany(NotaIngreso::class, 'anulado_por');
    }

    public function movimientosInventarioRegistrados(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'registrado_por');
    }
    public function notasSalidaRegistradas(): HasMany
    {
        return $this->hasMany(NotaSalida::class, 'registrado_por');
    }

    public function notasSalidaConfirmadas(): HasMany
    {
        return $this->hasMany(NotaSalida::class, 'confirmado_por');
    }

    public function notasSalidaAnuladas(): HasMany
    {
        return $this->hasMany(NotaSalida::class, 'anulado_por');
    }
    public function alertasStockAtendidas(): HasMany
    {
        return $this->hasMany(AlertaStock::class, 'atendida_por');
    }

    public function alertasStockResueltas(): HasMany
    {
        return $this->hasMany(AlertaStock::class, 'resuelta_por');
    }

}
