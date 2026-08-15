<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaProveedorDetalle extends Model
{
    use HasFactory;

    protected $table = 'factura_proveedor_detalles';

    protected $fillable = [
        'factura_proveedor_id',
        'orden_compra_detalle_id',
        'nota_ingreso_detalle_id',
        'producto_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'descuento_porcentaje',
        'igv_porcentaje',
        'subtotal',
        'impuesto',
        'total',
        'costo_provisional_soles',
        'ajuste_inventario_soles',
        'diferencia_contable_soles',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'decimal:4',
            'descuento_porcentaje' => 'decimal:4',
            'igv_porcentaje' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'impuesto' => 'decimal:4',
            'total' => 'decimal:4',
            'costo_provisional_soles' => 'decimal:4',
            'ajuste_inventario_soles' => 'decimal:4',
            'diferencia_contable_soles' => 'decimal:4',
        ];
    }

    public function facturaProveedor(): BelongsTo
    {
        return $this->belongsTo(FacturaProveedor::class);
    }

    public function ordenCompraDetalle(): BelongsTo
    {
        return $this->belongsTo(OrdenCompraDetalle::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function notaIngresoDetalle(): BelongsTo
    {
        return $this->belongsTo(NotaIngresoDetalle::class);
    }

    public function costoUnitarioTotalDocumento(): float
    {
        $cantidad = (float) $this->cantidad;

        return $cantidad > 0 ? round((float) $this->total / $cantidad, 4) : 0.0;
    }

    public function costoUnitarioTotalSoles(): float
    {
        return round(
            $this->costoUnitarioTotalDocumento() * $this->facturaProveedor->factorSoles(),
            4
        );
    }

    public function cantidadRecibidaConFactura(): float
    {
        if ($this->nota_ingreso_detalle_id !== null) {
            return (float) $this->cantidad;
        }

        return (float) NotaIngresoDetalle::query()
            ->where('orden_compra_detalle_id', $this->orden_compra_detalle_id)
            ->whereHas('notaIngreso', fn($query) => $query
                ->where('factura_proveedor_id', $this->factura_proveedor_id)
                ->where('estado', 'CONFIRMADA'))
            ->sum('cantidad');
    }

    public function cantidadPendienteRecepcion(): float
    {
        $pendienteFactura = max(0, round(
            (float) $this->cantidad - $this->cantidadRecibidaConFactura(),
            3
        ));
        $pendienteOrden = $this->ordenCompraDetalle
            ? $this->ordenCompraDetalle->cantidadPendiente()
            : $pendienteFactura;

        return min($pendienteFactura, $pendienteOrden);
    }
}
