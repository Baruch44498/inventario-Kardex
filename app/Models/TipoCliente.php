<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoCliente extends Model
{
    use HasFactory;

    protected $table = 'tipos_cliente';

    protected $fillable = [
        'codigo',
        'nombre',
        'porcentaje_ganancia',
        'descripcion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje_ganancia' => 'decimal:2',
            'estado' => 'boolean',
        ];
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', true);
    }
}
