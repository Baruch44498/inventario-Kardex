<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CotizacionCliente extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (CotizacionCliente $cotizacion): void {
            if ($cotizacion->orden_operacion_id) {
                OrdenOperacion::query()
                    ->whereKey($cotizacion->orden_operacion_id)
                    ->whereNull('cotizacion_cliente_id')
                    ->update(['cotizacion_cliente_id' => $cotizacion->id]);
            }
        });
    }

    public const ESTADOS = [
        'ABIERTA',
        'CERRADA',
        'CONVERTIDA_EN_ORDEN',
        'ANULADA',
    ];

    public const ORIGENES = [
        'PROFORMA_ALMACEN',
        'DIRECTA_LOGISTICA',
    ];

    protected $table = 'cotizaciones_cliente';

    protected $fillable = [
        'proforma_id',
        'origen',
        'cliente_id',
        'tipo_orden_id',
        'cliente_direccion_id',
        'vehiculo_id',
        'descripcion_trabajo',
        'codigo_base',
        'version',
        'codigo',
        'cliente_documento',
        'cliente_nombre',
        'fecha_emision',
        'fecha_validez',
        'moneda',
        'tipo_cambio',
        'margen_cliente_porcentaje',
        'subtotal',
        'impuesto',
        'total',
        'costeo_sincronizado_en',
        'condiciones_pago',
        'condiciones_entrega',
        'observacion',
        'estado',
        'cotizado_por',
        'cerrado_por',
        'cerrado_en',
        'anulado_por',
        'anulado_en',
        'motivo_anulacion',
        'orden_operacion_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'fecha_emision' => 'date',
            'fecha_validez' => 'date',
            'tipo_cambio' => 'decimal:6',
            'margen_cliente_porcentaje' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'impuesto' => 'decimal:4',
            'total' => 'decimal:4',
            'costeo_sincronizado_en' => 'datetime',
            'cerrado_en' => 'datetime',
            'anulado_en' => 'datetime',
        ];
    }

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tipoOrden(): BelongsTo
    {
        return $this->belongsTo(TipoOrden::class);
    }

    public function clienteDireccion(): BelongsTo
    {
        return $this->belongsTo(ClienteDireccion::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CotizacionClienteDetalle::class);
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(CotizacionPresupuesto::class);
    }

    public function componentes(): HasMany
    {
        return $this->hasMany(CotizacionComponente::class)
            ->orderBy('orden_secuencia');
    }

    public function ordenesOperacion(): HasMany
    {
        return $this->hasMany(OrdenOperacion::class);
    }

    public function cotizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cotizado_por');
    }

    public function cerrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function ordenOperacion(): BelongsTo
    {
        return $this->belongsTo(OrdenOperacion::class);
    }

    public function esEditable(): bool
    {
        return $this->estado === 'ABIERTA';
    }

    public function puedeCrearVersion(): bool
    {
        return in_array(
            $this->estado,
            ['CERRADA', 'CONVERTIDA_EN_ORDEN'],
            true
        );
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'ANULADA';
    }

    public function simboloMoneda(): string
    {
        return $this->moneda === 'USD' ? 'US$' : 'S/';
    }

    public function estadoVisual(): string
    {
        return $this->orden_operacion_id !== null
            ? 'Convertida'
            : match ($this->estado) {
                'ABIERTA' => 'Abierta',
                'CERRADA' => 'Cerrada',
                'CONVERTIDA_EN_ORDEN' => 'Convertida',
                'ANULADA' => 'Anulada',
                default => str_replace('_', ' ', ucfirst(strtolower($this->estado))),
            };
    }

    public function tonoEstadoVisual(): string
    {
        if ($this->orden_operacion_id !== null || $this->estado === 'CONVERTIDA_EN_ORDEN') {
            return 'success';
        }

        return match ($this->estado) {
            'CERRADA' => 'neutral',
            'ANULADA' => 'danger',
            'ABIERTA' => 'info',
            default => 'neutral',
        };
    }

    public function esDirecta(): bool
    {
        return $this->origen === 'DIRECTA_LOGISTICA';
    }

    public function origenVisible(): string
    {
        return $this->esDirecta()
            ? 'Creada directamente por Logística'
            : 'Originada en una proforma de Almacén';
    }

    public function puedeConvertirseEnOrden(): bool
    {
        return $this->proforma_id === null
            && in_array($this->estado, ['ABIERTA', 'CERRADA'], true)
            && $this->ordenesOperacion()->doesntExist();
    }

    public function tieneContextoOperativoCompleto(): bool
    {
        $componentes = $this->relationLoaded('componentes')
            ? $this->componentes
            : $this->componentes()->with('tipoOrden')->get();

        if ($componentes->isNotEmpty()) {
            return $componentes->every(function ($componente): bool {
                if (
                    ! $componente->tipoOrden
                    || trim((string) $componente->descripcion_componente) === ''
                ) {
                    return false;
                }

                return match ($componente->tipoOrden->codigo) {
                    'OM' => $componente->vehiculo_id !== null,
                    'OP' => $componente->vehiculo_id === null,
                    'OS' => true,
                    default => false,
                };
            });
        }

        if (! $this->tipoOrden || trim((string) $this->descripcion_trabajo) === '') {
            return false;
        }

        return $this->tipoOrden->codigo !== 'OM' || $this->vehiculo_id !== null;
    }

    public function esVentaDirecta(): bool
    {
        return $this->proforma_id !== null;
    }
}
