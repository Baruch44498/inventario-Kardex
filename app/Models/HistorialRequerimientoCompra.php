<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialRequerimientoCompra extends Model
{
    protected $table = 'historial_requerimientos_compra';

    public $timestamps = false;

    protected $fillable = [
        'requisicion_id',
        'estado_anterior',
        'estado_nuevo',
        'observacion',
        'registrado_por',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
