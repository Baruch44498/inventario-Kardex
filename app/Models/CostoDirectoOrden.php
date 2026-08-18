<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostoDirectoOrden extends Model
{
    use HasFactory;

    public const TIPOS = [
        'MANO_OBRA' => 'Mano de obra',
        'SERVICIO_TERCERO' => 'Servicio de terceros',
        'TRANSPORTE' => 'Transporte',
        'VIATICOS' => 'Viáticos',
        'EPP_CONSUMIBLES' => 'EPP y consumibles',
        'OTRO' => 'Otro costo directo',
    ];

    public const UNIDADES = [
        'HORA' => 'Hora',
        'DIA' => 'Día / jornada',
        'SERVICIO' => 'Servicio',
        'VIAJE' => 'Viaje',
        'UNIDAD' => 'Unidad',
        'GLOBAL' => 'Global',
    ];

    protected $table = 'costos_directos_orden';

    protected $fillable = [
        'orden_operacion_id',
        'tipo',
        'fecha_costo',
        'descripcion',
        'proveedor_id',
        'cantidad',
        'unidad',
        'costo_unitario_soles',
        'total_soles',
        'documento_referencia',
        'observacion',
        'estado',
        'registrado_por',
        'registrado_en',
        'anulado_por',
        'anulado_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_costo' => 'date',
            'cantidad' => 'decimal:3',
            'costo_unitario_soles' => 'decimal:4',
            'total_soles' => 'decimal:4',
            'registrado_en' => 'datetime',
            'anulado_en' => 'datetime',
        ];
    }

    public function ordenOperacion(): BelongsTo
    {
        return $this->belongsTo(OrdenOperacion::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function tipoVisible(): string
    {
        return self::TIPOS[$this->tipo] ?? str_replace('_', ' ', ucfirst(strtolower($this->tipo)));
    }

    public function unidadVisible(): string
    {
        return self::UNIDADES[$this->unidad] ?? $this->unidad;
    }

    public function estaVigente(): bool
    {
        return $this->estado === 'VIGENTE';
    }
}
