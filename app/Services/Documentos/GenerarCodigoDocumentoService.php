<?php

namespace App\Services\Documentos;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GenerarCodigoDocumentoService
{
    /**
     * @template TResult
     *
     * @param Closure(string): TResult $callback
     * @return TResult
     */
    public function usarSiguiente(
        string $tabla,
        string $prefijo,
        string $fechaDocumento,
        Closure $callback
    ): mixed {
        $anio = Carbon::parse($fechaDocumento)->format('y');
        $nombreBloqueo = "hidroil:{$tabla}:{$anio}";

        if (DB::connection()->getDriverName() !== 'mysql') {
            return $callback(
                $this->siguienteCodigo($tabla, $prefijo, $anio)
            );
        }

        $resultado = DB::selectOne(
            'SELECT GET_LOCK(?, 10) AS adquirido',
            [$nombreBloqueo]
        );

        if ((int) ($resultado->adquirido ?? 0) !== 1) {
            throw new RuntimeException(
                'No se pudo reservar el correlativo. Intenta nuevamente.'
            );
        }

        try {
            return $callback(
                $this->siguienteCodigo($tabla, $prefijo, $anio)
            );
        } finally {
            DB::selectOne(
                'SELECT RELEASE_LOCK(?) AS liberado',
                [$nombreBloqueo]
            );
        }
    }

    private function siguienteCodigo(
        string $tabla,
        string $prefijo,
        string $anio
    ): string {
        $ultimoCodigo = DB::table($tabla)
            ->where('codigo', 'like', "{$prefijo}-%-{$anio}")
            ->orderByDesc('id')
            ->value('codigo');

        $secuencia = 1;
        $patron = '/^'
            . preg_quote($prefijo, '/')
            . '-(\d+)-'
            . preg_quote($anio, '/')
            . '$/';

        if (
            is_string($ultimoCodigo)
            && preg_match($patron, $ultimoCodigo, $coincidencias)
        ) {
            $secuencia = (int) $coincidencias[1] + 1;
        }

        do {
            $codigo = sprintf(
                '%s-%03d-%s',
                $prefijo,
                $secuencia,
                $anio
            );
            $secuencia++;
        } while (
            DB::table($tabla)
            ->where('codigo', $codigo)
            ->exists()
        );

        return $codigo;
    }
}
