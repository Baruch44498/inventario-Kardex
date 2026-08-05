<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    public const LIMITE_SIN_DOCUMENTO_CENTIMOS = 70000;

    protected $fillable = [
        'tipo_cliente_id',
        'tipo_documento',
        'numero_documento',
        'ruc',
        'razon_social',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'nombre_comercial',
        'correo',
        'telefono',
        'contacto',
        'es_mostrador',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'es_mostrador' => 'boolean',
            'estado' => 'boolean',
        ];
    }

    public function tipoCliente(): BelongsTo
    {
        return $this->belongsTo(TipoCliente::class);
    }

    public function direcciones(): HasMany
    {
        return $this->hasMany(ClienteDireccion::class);
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    public function ordenesOperacion(): HasMany
    {
        return $this->hasMany(OrdenOperacion::class);
    }

    public function proformas(): HasMany
    {
        return $this->hasMany(Proforma::class);
    }

    public function cotizacionesCliente(): HasMany
    {
        return $this->hasMany(CotizacionCliente::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', true);
    }

    public function requiereDireccionFiscal(): bool
    {
        return $this->tipo_documento === 'RUC' && ! $this->es_mostrador;
    }

    public function tieneDireccionFiscalActiva(): bool
    {
        return $this->direcciones()
            ->fiscalesActivas()
            ->exists();
    }

    public function nombreFacturacion(): string
    {
        return match ($this->tipo_documento) {
            'RUC' => $this->razon_social,

            'DNI' => $this->unirNombrePersonal(),

            'CE', 'SIN_DOCUMENTO' => $this->nombres
                ?: $this->razon_social,

            default => $this->razon_social,
        };
    }

    public function nombreVisible(): string
    {
        if (
            $this->tipo_documento === 'RUC'
            && filled($this->nombre_comercial)
        ) {
            return $this->nombre_comercial;
        }

        return $this->nombreFacturacion();
    }

    public function documentoVisible(): string
    {
        if ($this->esSinDocumento()) {
            return 'Sin documento';
        }

        $documento = $this->numero_documento ?: $this->ruc;

        return $documento
            ? "{$this->tipo_documento} {$documento}"
            : $this->tipo_documento;
    }

    public function esSinDocumento(): bool
    {
        return $this->tipo_documento === 'SIN_DOCUMENTO';
    }

    /**
     * Preparado para el futuro flujo de venta directa.
     * El bloqueo se aplicará al confirmar la venta, no al crear clientes.
     */
    public function excedeLimiteSinDocumento(
        float|int|string $totalPen
    ): bool {
        if (! $this->esSinDocumento()) {
            return false;
        }

        $centimos = (int) round(((float) $totalPen) * 100);

        return $centimos > self::LIMITE_SIN_DOCUMENTO_CENTIMOS;
    }

    private function unirNombrePersonal(): string
    {
        $nombre = collect([
            $this->nombres,
            $this->apellido_paterno,
            $this->apellido_materno,
        ])
            ->filter(fn($valor) => filled($valor))
            ->implode(' ');

        return $nombre ?: $this->razon_social;
    }
}
