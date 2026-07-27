<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marca extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function productosPrincipales(): HasMany
    {
        return $this->hasMany(Producto::class, 'marca_principal_id');
    }
}
