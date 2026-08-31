<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaIngreso extends Model
{
    use HasFactory;

    protected $table = 'notas_ingreso';

    protected $fillable = [
        'orden_compra_id',
        'orden_operacion_id',
        'orden_area_id',
        'area_trabajo',
        'factura_proveedor_id',
        'motivo_ingreso',
        'nota_salida_id',
        'proforma_id',
        'devuelto_por_empleado_id',
        'devuelto_por_nombre',
        'devuelto_por_dni',
        'codigo',
        'fecha_ingreso',
        'numero_guia_remision',
        'observacion',
        'estado',
        'registrado_por',
        'confirmado_por',
        'confirmado_en',
        'anulado_por',
        'anulado_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'confirmado_en' => 'datetime',
            'anulado_en' => 'datetime',
        ];
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class);
    }

    public function facturaProveedor(): BelongsTo
    {
        return $this->belongsTo(FacturaProveedor::class);
    }

    public function ordenOperacion(): BelongsTo
    {
        return $this->belongsTo(OrdenOperacion::class);
    }

    public function ordenArea(): BelongsTo
    {
        return $this->belongsTo(OrdenArea::class);
    }

    public function devueltoPorEmpleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'devuelto_por_empleado_id');
    }

    public function notaSalidaOrigen(): BelongsTo
    {
        return $this->belongsTo(NotaSalida::class, 'nota_salida_id');
    }

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function confirmador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(NotaIngresoDetalle::class);
    }

    public function estaConfirmada(): bool
    {
        return $this->estado === 'CONFIRMADA';
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'ANULADA';
    }

    public function motivoVisible(): string
    {
        return match ($this->motivo_ingreso) {
            'DEVOLUCION_HERRAMIENTA' => 'Devolución de herramienta',
            'RETORNO_MATERIAL' => 'Retorno de material no utilizado',
            'DEVOLUCION_MATERIAL_MALOGRADO' => 'Devolución de material malogrado',
            'REPOSICION_PRESTAMO' => 'Reposición de préstamo',
            default => 'Recepción de compra',
        };
    }
}
