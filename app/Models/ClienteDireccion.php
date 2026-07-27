<?php

namespace App\Models;

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
        'direccion',
        'destino',
        'referencia',
        'es_principal',
        'estado',
    ];

    protected function casts(): array
    {
        return [
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
}
