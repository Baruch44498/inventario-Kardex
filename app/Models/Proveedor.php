<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = [
        'ruc',
        'razon_social',
        'nombre_comercial',
        'correo',
        'telefono',
        'contacto',
        'ciudad',
        'departamento',
        'direccion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }

    public function cotizacionDetalles(): HasManyThrough
    {
        return $this->hasManyThrough(
            CotizacionDetalle::class,
            Cotizacion::class,
            'proveedor_id',
            'cotizacion_id'
        );
    }

    public function ordenesCompra(): HasMany
    {
        return $this->hasMany(OrdenCompra::class);
    }

    public function facturasProveedor(): HasMany
    {
        return $this->hasMany(FacturaProveedor::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', true);
    }

    public function nombreVisible(): string
    {
        return $this->nombre_comercial ?: $this->razon_social;
    }

    public function ubicacionVisible(): string
    {
        return collect([$this->ciudad, $this->departamento])
            ->filter()
            ->implode(', ');
    }
}
