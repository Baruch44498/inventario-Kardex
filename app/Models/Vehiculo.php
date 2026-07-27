<?php

namespace App\Models;

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
        'descripcion',
        'procedencia',
        'estado',
    ];

    protected function casts(): array
    {
        return [
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
}
