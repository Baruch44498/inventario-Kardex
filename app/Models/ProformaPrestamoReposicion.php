<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaPrestamoReposicion extends Model
{
    use HasFactory;

    protected $table = 'proforma_prestamo_reposiciones';

    protected $fillable = [
        'proforma_detalle_id',
        'nota_ingreso_id',
        'nota_ingreso_detalle_id',
        'cantidad',
        'observacion',
        'registrado_por',
        'registrado_en',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'registrado_en' => 'datetime',
        ];
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(ProformaDetalle::class, 'proforma_detalle_id');
    }

    public function notaIngresoDetalle(): BelongsTo
    {
        return $this->belongsTo(NotaIngresoDetalle::class);
    }

    public function notaIngreso(): BelongsTo
    {
        return $this->belongsTo(NotaIngreso::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
