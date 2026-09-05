<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantillaCosteoArea extends Model
{
    protected $table = 'plantilla_costeo_areas';

    protected $fillable = ['plantilla_costeo_id', 'area_padre_id', 'nombre', 'orden_secuencia'];

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaCosteo::class, 'plantilla_costeo_id');
    }
}
