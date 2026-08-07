<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProformaDetalle extends Model
{
    use HasFactory;

    public const TRATAMIENTOS = ['VENTA', 'PRESTAMO'];

    protected $fillable = [
        'proforma_id',
        'producto_id',
        'codigo_producto',
        'descripcion',
        'unidad_medida',
        'cantidad',
        'tratamiento',
        'costo_referencia',
        'margen_sugerido',
        'precio_sugerido',
        'precio_unitario',
        'igv_modo',
        'igv_porcentaje',
        'subtotal',
        'impuesto',
        'total',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'costo_referencia' => 'decimal:4',
            'margen_sugerido' => 'decimal:4',
            'precio_sugerido' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'igv_porcentaje' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'impuesto' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function notasSalidaDetalles(): HasMany
    {
        return $this->hasMany(NotaSalidaDetalle::class);
    }

    public function notasIngresoDetalles(): HasMany
    {
        return $this->hasMany(NotaIngresoDetalle::class);
    }

    public function reposiciones(): HasMany
    {
        return $this->hasMany(
            ProformaPrestamoReposicion::class,
            'proforma_detalle_id'
        );
    }

    public function esVenta(): bool
    {
        return $this->tratamiento === 'VENTA';
    }

    public function esPrestamo(): bool
    {
        return $this->tratamiento === 'PRESTAMO';
    }

    public function cantidadDespachada(): float
    {
        $total = $this->notasSalidaDetalles()
            ->whereHas('notaSalida', fn($nota) => $nota->where('estado', 'CONFIRMADA'))
            ->sum('cantidad');

        return round((float) $total, 3);
    }

    public function cantidadPendienteSalida(): float
    {
        return max(0.0, round((float) $this->cantidad - $this->cantidadDespachada(), 3));
    }

    public function cantidadPrestadaFisicamente(): float
    {
        if (! $this->esPrestamo()) {
            return 0.0;
        }

        $total = $this->notasSalidaDetalles()
            ->where('tratamiento', 'PRESTAMO_EXTERNO')
            ->whereHas('notaSalida', fn($nota) => $nota->where('estado', 'CONFIRMADA'))
            ->sum('cantidad');

        return round((float) $total, 3);
    }

    public function cantidadRepuesta(): float
    {
        if (! $this->esPrestamo()) {
            return 0.0;
        }

        $total = $this->relationLoaded('reposiciones')
            ? $this->reposiciones->sum('cantidad')
            : $this->reposiciones()->sum('cantidad');

        return round((float) $total, 3);
    }

    public function cantidadPendienteReposicion(): float
    {
        if (! $this->esPrestamo()) {
            return 0.0;
        }

        return max(
            0.0,
            round($this->cantidadPrestadaFisicamente() - $this->cantidadRepuesta(), 3)
        );
    }

    public function prestamoRegularizado(): bool
    {
        return $this->esPrestamo()
            && $this->cantidadPendienteSalida() <= 0.0001
            && $this->cantidadPendienteReposicion() <= 0.0001;
    }

    public function precioFueAjustado(): bool
    {
        return $this->precio_sugerido !== null
            && abs(
                (float) $this->precio_unitario
                    - (float) $this->precio_sugerido
            ) > 0.0001;
    }
}
