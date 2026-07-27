<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'ruc',
        'razon_social',
        'correo',
        'telefono',
        'contacto',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function direcciones(): HasMany
    {
        return $this->hasMany(ClienteDireccion::class);
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    public function ordenesOperacion(): HasMany
    {
        return $this->hasMany(OrdenOperacion::class);
    }
}