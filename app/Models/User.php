<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public $timestamps = false;

    protected $fillable = [
        'role_id',
        'username',
        'email',
        'password',
        'estado',
        'ultimo_acceso_en',
        'fecha_creacion',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'estado' => 'boolean',
            'ultimo_acceso_en' => 'datetime',
            'fecha_creacion' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function ordenesOperacionCreadas(): HasMany
    {
        return $this->hasMany(OrdenOperacion::class, 'creado_por');
    }

    public function ordenesOperacionAnuladas(): HasMany
    {
        return $this->hasMany(OrdenOperacion::class, 'anulado_por');
    }

    public function proformasRegistradas(): HasMany
    {
        return $this->hasMany(Proforma::class, 'registrado_por');
    }

    public function proformasEmitidas(): HasMany
    {
        return $this->hasMany(Proforma::class, 'emitido_por');
    }

    public function proformasEnviadas(): HasMany
    {
        return $this->hasMany(Proforma::class, 'enviado_por');
    }

    public function proformasAnuladas(): HasMany
    {
        return $this->hasMany(Proforma::class, 'anulado_por');
    }

    public function cotizacionesClienteRegistradas(): HasMany
    {
        return $this->hasMany(CotizacionCliente::class, 'cotizado_por');
    }

    public function tieneRol(string ...$codigos): bool
    {
        return in_array($this->role?->codigo, $codigos, true);
    }

    public function esAdministrador(): bool
    {
        return $this->tieneRol('ADMINISTRADOR');
    }

    public function permisos(): array
    {
        $codigo = $this->role?->codigo;

        if (! $codigo || ! $this->estado) {
            return [];
        }

        return config("hidroil_permisos.roles.{$codigo}.permisos", []);
    }

    public function puede(string $permiso): bool
    {
        $permisos = $this->permisos();

        return in_array('*', $permisos, true)
            || in_array($permiso, $permisos, true);
    }

    public function puedeAlguno(string ...$permisos): bool
    {
        foreach ($permisos as $permiso) {
            if ($this->puede($permiso)) {
                return true;
            }
        }

        return false;
    }

    public function nombreVisible(): string
    {
        return $this->username ?: $this->email;
    }
}
