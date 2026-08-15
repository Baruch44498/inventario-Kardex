<?php

namespace App\Services\Productos;

use App\Models\Producto;

final class GenerarCodigoProductoService
{
    public function siguiente(): string
    {
        $mayor = Producto::query()
            ->pluck('codigo')
            ->filter(fn($codigo): bool => ctype_digit(trim((string) $codigo)))
            ->map(fn($codigo): int => (int) $codigo)
            ->max();

        return (string) (($mayor ?? 0) + 1);
    }
}
