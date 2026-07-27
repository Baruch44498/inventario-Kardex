<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadMedida extends Model
{
    use HasFactory;

    protected $table = 'unidades_medida';
    protected $fillable = ['codigo', 'nombre', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }
}
