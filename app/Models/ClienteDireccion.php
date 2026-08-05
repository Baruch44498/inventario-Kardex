<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClienteDireccion extends Model
{
    use HasFactory;

    protected $table = 'cliente_direcciones';

    protected $fillable = [
        'cliente_id',
        'ciudad',
        'departamento',
        'provincia',
        'distrito',
        'direccion',
        'destino',
        'referencia',
        'es_fiscal',
        'es_principal',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'es_fiscal' => 'boolean',
            'es_principal' => 'boolean',
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

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('estado', true);
    }

    public function scopeFiscalesActivas(Builder $query): Builder
    {
        return $query
            ->where('es_fiscal', true)
            ->where('estado', true);
    }

    public function ubicacionVisible(): string
    {
        return collect([
            $this->distrito,
            $this->provincia,
            $this->departamento,
        ])->filter()->implode(', ');
    }
}
