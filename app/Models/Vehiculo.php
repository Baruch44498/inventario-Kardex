<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehiculo extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'placa',
        'codigo_interno',
        'marca',
        'modelo',
        'anio',
        'color',
        'vin',
        'descripcion',
        'procedencia',
        'es_comodin',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'es_comodin' => 'boolean',
            'estado' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function ordenesOperacion(): HasMany
    {
        return $this->hasMany(OrdenOperacion::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', true);
    }

    public function identificadorVisible(): string
    {
        return $this->placa ?: ($this->codigo_interno ?: 'Sin identificador');
    }

    public function descripcionVisible(): string
    {
        $marcaModelo = trim(collect([$this->marca, $this->modelo])->filter()->implode(' '));

        return $marcaModelo ?: ($this->descripcion ?: 'Vehículo sin descripción');
    }
}
