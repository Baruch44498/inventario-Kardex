<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
