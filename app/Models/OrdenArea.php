<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenArea extends Model
{
    use HasFactory;

    protected $table = 'orden_areas';

    protected $fillable = [
        'orden_operacion_id', 'cotizacion_area_id', 'area_padre_id', 'nombre',
        'nombre_normalizado', 'orden_secuencia', 'origen', 'estado',
    ];

    protected function casts(): array
    {
        return ['orden_secuencia' => 'integer'];
    }

    public function ordenOperacion(): BelongsTo
    {
        return $this->belongsTo(OrdenOperacion::class);
    }

    public function cotizacionArea(): BelongsTo
    {
        return $this->belongsTo(CotizacionArea::class);
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'area_padre_id');
    }

    public function subareas(): HasMany
    {
        return $this->hasMany(self::class, 'area_padre_id')->orderBy('orden_secuencia');
    }

    public function materialesPlanificados(): HasMany
    {
        return $this->hasMany(MaterialPlanificadoOrdenArea::class, 'orden_area_id');
    }

    public function notasSalida(): HasMany
    {
        return $this->hasMany(NotaSalida::class, 'orden_area_id');
    }

    public function notasIngreso(): HasMany
    {
        return $this->hasMany(NotaIngreso::class, 'orden_area_id');
    }
}
