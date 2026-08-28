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
        'OTRO' => 'Otro costo interno',
    ];

    public const UNIDADES = [
        'HORA' => 'Hora',
        'DIA' => 'Día / jornada',
        'SERVICIO' => 'Servicio',
        'VIAJE' => 'Viaje',
        'UNIDAD' => 'Unidad',
        'METRO' => 'Metro',
        'KILOGRAMO' => 'Kilogramo',
        'LITRO' => 'Litro',
        'GLOBAL' => 'Global',
    ];

    public const UNIDADES_POR_TIPO = [
        'MANO_OBRA' => ['HORA', 'DIA'],
        'SERVICIO_TERCERO' => ['HORA', 'DIA', 'SERVICIO', 'GLOBAL'],
        'TRANSPORTE' => ['VIAJE', 'SERVICIO', 'GLOBAL'],
        'VIATICOS' => ['DIA', 'UNIDAD', 'GLOBAL'],
        'OTRO' => ['UNIDAD', 'GLOBAL'],
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
        'componente_id',
        'producto_id',
        'tipo_costo',
        'grupo_costo',
        'descripcion',
        'cantidad',
        'unidad',
        'moneda',
        'tipo_cambio',
        'costo_unitario',
        'margen_porcentaje',
        'carga_social_porcentaje',
        'carga_social_original',
        'igv_modo',
        'igv_porcentaje',
        'igv_venta_porcentaje',
        'costo_neto_original',
        'igv_original',
        'costo_total_original',
        'costo_neto_soles',
        'igv_soles',
        'costo_total_soles',
        'costo_neto_dolares',
        'igv_dolares',
        'costo_total_dolares',
        'precio_venta_neto_original',
        'igv_venta_original',
        'precio_venta_total_original',
        'utilidad_estimada_original',
        'igv_por_pagar_original',
        'precio_venta_neto_soles',
        'igv_venta_soles',
        'precio_venta_total_soles',
        'utilidad_estimada_soles',
        'igv_por_pagar_soles',
        'precio_venta_neto_dolares',
        'igv_venta_dolares',
        'precio_venta_total_dolares',
        'utilidad_estimada_dolares',
        'igv_por_pagar_dolares',
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
            'margen_porcentaje' => 'decimal:4',
            'carga_social_porcentaje' => 'decimal:4',
            'carga_social_original' => 'decimal:4',
            'igv_porcentaje' => 'decimal:4',
            'igv_venta_porcentaje' => 'decimal:4',
            'costo_neto_original' => 'decimal:4',
            'igv_original' => 'decimal:4',
            'costo_total_original' => 'decimal:4',
            'costo_neto_soles' => 'decimal:4',
            'igv_soles' => 'decimal:4',
            'costo_total_soles' => 'decimal:4',
            'costo_neto_dolares' => 'decimal:4',
            'igv_dolares' => 'decimal:4',
            'costo_total_dolares' => 'decimal:4',
            'precio_venta_neto_original' => 'decimal:4',
            'igv_venta_original' => 'decimal:4',
            'precio_venta_total_original' => 'decimal:4',
            'utilidad_estimada_original' => 'decimal:4',
            'igv_por_pagar_original' => 'decimal:4',
            'precio_venta_neto_soles' => 'decimal:4',
            'igv_venta_soles' => 'decimal:4',
            'precio_venta_total_soles' => 'decimal:4',
            'utilidad_estimada_soles' => 'decimal:4',
            'igv_por_pagar_soles' => 'decimal:4',
            'precio_venta_neto_dolares' => 'decimal:4',
            'igv_venta_dolares' => 'decimal:4',
            'precio_venta_total_dolares' => 'decimal:4',
            'utilidad_estimada_dolares' => 'decimal:4',
            'igv_por_pagar_dolares' => 'decimal:4',
            'registrado_en' => 'datetime',
            'anulado_en' => 'datetime',
        ];
    }

    public function cotizacionCliente(): BelongsTo
    {
        return $this->belongsTo(CotizacionCliente::class);
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(CotizacionComponente::class, 'componente_id');
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

    public static function unidadesParaTipo(?string $tipo): array
    {
        return self::UNIDADES_POR_TIPO[$tipo] ?? [];
    }

    public static function unidadDeProducto(Producto $producto): ?string
    {
        $codigo = strtoupper(trim((string) $producto->unidadMedida?->codigo));

        return $codigo !== '' ? $codigo : null;
    }
}
