<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AliasProductoProveedor extends Model
{
    protected $table = 'aliases_producto_proveedor';

    protected $fillable = [
        'proveedor_id', 'producto_id', 'codigo_proveedor',
        'descripcion_proveedor', 'clave', 'confirmado_por',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public static function clave(?string $codigo, ?string $descripcion): ?string
    {
        $codigo = preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower(trim((string) $codigo)))) ?: '';
        $descripcion = Str::ascii(mb_strtolower(trim((string) $descripcion)));
        $descripcion = trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $descripcion) ?: '') ?: '');

        if ($codigo === '' && $descripcion === '') {
            return null;
        }

        return hash('sha256', json_encode([$codigo, $descripcion], JSON_UNESCAPED_UNICODE));
    }
}
