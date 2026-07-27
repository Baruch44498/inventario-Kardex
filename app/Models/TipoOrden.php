<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoOrden extends Model
{
    use HasFactory;

    protected $table = 'tipos_orden';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function ordenesOperacion(): HasMany
    {
        return $this->hasMany(OrdenOperacion::class);
    }
}
