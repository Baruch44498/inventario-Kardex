<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    public function ordenesCompra(): HasMany
    {
        return $this->hasMany(OrdenCompra::class);
    }

    public function facturasProveedor(): HasMany
    {
        return $this->hasMany(FacturaProveedor::class);
    }
}
