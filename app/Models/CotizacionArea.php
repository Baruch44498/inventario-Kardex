<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CotizacionArea extends Model
{
    use HasFactory;

    public const ORIGENES = ['MANUAL', 'EXCEL', 'LEGADO'];

    protected $table = 'cotizacion_areas';

    protected $fillable = [
        'cotizacion_cliente_id', 'componente_origen_id', 'area_padre_id',
        'nombre', 'nombre_normalizado', 'orden_secuencia', 'origen', 'estado',
    ];

    protected function casts(): array
    {
        return ['orden_secuencia' => 'integer'];
    }

    public function cotizacionCliente(): BelongsTo
    {
        return $this->belongsTo(CotizacionCliente::class);
    }

    public function componenteOrigen(): BelongsTo
    {
        return $this->belongsTo(CotizacionComponente::class, 'componente_origen_id');
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'area_padre_id');
    }

    public function subareas(): HasMany
    {
        return $this->hasMany(self::class, 'area_padre_id')->orderBy('orden_secuencia');
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(CotizacionPresupuesto::class, 'cotizacion_area_id');
    }

    public function areasDeOrden(): HasMany
    {
        return $this->hasMany(OrdenArea::class, 'cotizacion_area_id');
    }
}
