<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repisa extends Model
{
    use HasFactory;

    protected $fillable = ['codigo', 'descripcion', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class);
    }
    public function notaIngresoDetalles(): HasMany
    {
        return $this->hasMany(NotaIngresoDetalle::class);
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }
    public function notaSalidaDetalles(): HasMany
    {
        return $this->hasMany(NotaSalidaDetalle::class);
    }
        public function alertasStock(): HasMany
    {
        return $this->hasMany(AlertaStock::class);
    }
}
