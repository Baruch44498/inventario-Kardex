<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantillaCosteo extends Model
{
    use HasFactory;

    protected $table = 'plantillas_costeo';

    protected $fillable = [
        'tipo_orden_id',
        'nombre',
        'descripcion',
        'origen',
        'activo',
        'creado_por',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function tipoOrden(): BelongsTo
    {
        return $this->belongsTo(TipoOrden::class);
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(PlantillaCosteoPartida::class)
            ->orderBy('orden_secuencia');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
