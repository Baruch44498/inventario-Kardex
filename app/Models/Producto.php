<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = [
        'unidad_medida_id',
        'marca_principal_id',
        'codigo',
        'descripcion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    public function marcaPrincipal(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_principal_id');
    }

    public function porcentajes(): HasMany
    {
        return $this->hasMany(ProductoPorcentaje::class);
    }

    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class);
    }
    public function requisicionDetalles(): HasMany
    {
        return $this->hasMany(RequisicionDetalle::class);
    }
    public function cotizacionDetalles(): HasMany
    {
        return $this->hasMany(CotizacionDetalle::class);
    }
    public function solicitudCompraDetalles(): HasMany
    {
        return $this->hasMany(SolicitudCompraDetalle::class);
    }

    public function ordenCompraDetalles(): HasMany
    {
        return $this->hasMany(OrdenCompraDetalle::class);
    }

    public function facturaProveedorDetalles(): HasMany
    {
        return $this->hasMany(FacturaProveedorDetalle::class);
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