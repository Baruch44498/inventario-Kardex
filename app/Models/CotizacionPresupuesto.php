<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionPresupuesto extends Model
{
    use HasFactory;

    public const TIPOS = [
        'MATERIAL' => 'Material de inventario',
        'MANO_OBRA' => 'Mano de obra',
        'SERVICIO_TERCERO' => 'Servicio de terceros',
        'TRANSPORTE' => 'Transporte',
        'VIATICOS' => 'Viáticos',
        'EPP_CONSUMIBLES' => 'EPP y consumibles',
        'OTRO' => 'Otro costo interno',
    ];

    public const UNIDADES = [
        'HORA' => 'Hora',
        'DIA' => 'Día / jornada',
        'SERVICIO' => 'Servicio',
        'VIAJE' => 'Viaje',
        'UNIDAD' => 'Unidad',
        'GLOBAL' => 'Global',
    ];

    public const MONEDAS = [
        'PEN' => 'Soles',
        'USD' => 'Dólares',
    ];

    public const MODOS_IGV = [
        'AGREGAR' => 'Agregar IGV',
        'INCLUIDO' => 'IGV incluido',
        'NO_APLICA' => 'No aplica IGV',
    ];

    protected $fillable = [
        'cotizacion_cliente_id',
        'producto_id',
        'tipo_costo',
        'descripcion',
        'cantidad',
        'unidad',
        'moneda',
        'tipo_cambio',
        'costo_unitario',
        'carga_social_porcentaje',
        'carga_social_original',
        'igv_modo',
        'igv_porcentaje',
        'costo_neto_original',
        'igv_original',
        'costo_total_original',
        'costo_neto_soles',
        'igv_soles',
        'costo_total_soles',
        'costo_neto_dolares',
        'igv_dolares',
        'costo_total_dolares',
        'observacion',
        'estado',
        'registrado_por',
        'actualizado_por',
        'registrado_en',
        'anulado_por',
        'anulado_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'tipo_cambio' => 'decimal:6',
            'costo_unitario' => 'decimal:4',
            'carga_social_porcentaje' => 'decimal:4',
            'carga_social_original' => 'decimal:4',
            'igv_porcentaje' => 'decimal:4',
            'costo_neto_original' => 'decimal:4',
            'igv_original' => 'decimal:4',
            'costo_total_original' => 'decimal:4',
            'costo_neto_soles' => 'decimal:4',
            'igv_soles' => 'decimal:4',
            'costo_total_soles' => 'decimal:4',
            'costo_neto_dolares' => 'decimal:4',
            'igv_dolares' => 'decimal:4',
            'costo_total_dolares' => 'decimal:4',
            'registrado_en' => 'datetime',
            'anulado_en' => 'datetime',
        ];
    }

    public function cotizacionCliente(): BelongsTo
    {
        return $this->belongsTo(CotizacionCliente::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function estaVigente(): bool
    {
        return $this->estado === 'VIGENTE';
    }

    public function tipoVisible(): string
    {
        return self::TIPOS[$this->tipo_costo] ?? $this->tipo_costo;
    }

    public function unidadVisible(): string
    {
        return self::UNIDADES[$this->unidad] ?? $this->unidad;
    }
}
