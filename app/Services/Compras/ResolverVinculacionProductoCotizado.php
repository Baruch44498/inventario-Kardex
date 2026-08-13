<?php

namespace App\Services\Compras;

use App\Models\Producto;
use App\Models\Requisicion;
use App\Models\RequisicionDetalle;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ResolverVinculacionProductoCotizado
{
    /**
     * Busca primero dentro del requerimiento y después en el catálogo. Solo
     * selecciona automáticamente coincidencias exactas y únicas; una
     * similitud aproximada siempre queda pendiente de confirmación humana.
     *
     * @return array<string, mixed>
     */
    public function resolver(
        ?string $codigoDocumento,
        ?string $descripcionDocumento,
        ?Requisicion $requisicion = null
    ): array {
        $codigo = $this->normalizarCodigo((string) $codigoDocumento);
        $descripcion = $this->normalizarTexto((string) $descripcionDocumento);
        $requisicion?->loadMissing(['detalles.producto.unidadMedida']);
        $detalles = $requisicion?->detalles ?? collect();

        $exactasRequerimiento = $this->exactasRequerimiento(
            $detalles,
            $codigo,
            $descripcion
        );

        if ($exactasRequerimiento->count() === 1) {
            $detalle = $exactasRequerimiento->first();

            return $this->resultadoAutomatico(
                $detalle->producto,
                $detalle,
                'SOLICITADO',
                'EXACTA'
            );
        }

        $exactasCatalogo = $this->exactasCatalogo($codigo, $descripcion);

        if ($exactasCatalogo->count() === 1) {
            $producto = $exactasCatalogo->first();
            $detalle = $detalles->firstWhere('producto_id', $producto->id);

            return $this->resultadoAutomatico(
                $producto,
                $detalle,
                $detalle ? 'SOLICITADO' : 'ADICIONAL',
                'EXACTA_CATALOGO'
            );
        }

        $candidatosRequerimiento = $this->candidatosRequerimiento(
            $detalles,
            $codigo,
            $descripcion
        );
        $idsRequerimiento = $detalles->pluck('producto_id')->filter()->unique();
        $candidatosCatalogo = $this->candidatosCatalogo(
            $codigo,
            $descripcion,
            $idsRequerimiento
        );
        $candidatos = $candidatosRequerimiento
            ->concat($candidatosCatalogo)
            ->take(5)
            ->values()
            ->all();

        return [
            'producto_id' => null,
            'producto' => null,
            'requisicion_detalle_id' => null,
            'tipo_vinculacion' => null,
            'vinculacion_origen' => null,
            'coincidencia' => $candidatos === [] ? 'SIN_COINCIDENCIA' : 'SUGERIDA',
            'candidatos' => $candidatos,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function lineasRequerimiento(?Requisicion $requisicion): array
    {
        if (! $requisicion) {
            return [];
        }

        $requisicion->loadMissing(['detalles.producto.unidadMedida']);

        return $requisicion->detalles
            ->filter(fn (RequisicionDetalle $detalle): bool => $detalle->producto !== null)
            ->map(fn (RequisicionDetalle $detalle): array => [
                'id' => $detalle->id,
                'producto_id' => $detalle->producto_id,
                'codigo' => $detalle->producto->codigo,
                'descripcion' => $detalle->producto->descripcion,
                'cantidad' => (float) $detalle->cantidad_solicitada,
                'unidad' => $this->unidadVisible($detalle->producto),
                'label' => $detalle->producto->codigo . ' — ' . $detalle->producto->descripcion,
            ])
            ->values()
            ->all();
    }

    /** @return Collection<int, RequisicionDetalle> */
    private function exactasRequerimiento(
        Collection $detalles,
        string $codigo,
        string $descripcion
    ): Collection {
        return $detalles->filter(function (RequisicionDetalle $detalle) use ($codigo, $descripcion): bool {
            if (! $detalle->producto) {
                return false;
            }

            if ($codigo !== '' && $codigo === $this->normalizarCodigo($detalle->producto->codigo)) {
                return true;
            }

            return $descripcion !== ''
                && $descripcion === $this->normalizarTexto($detalle->producto->descripcion);
        })->values();
    }

    /** @return Collection<int, Producto> */
    private function exactasCatalogo(string $codigo, string $descripcion): Collection
    {
        if ($codigo === '' && $descripcion === '') {
            return collect();
        }

        // La comparación normalizada se hace en PHP para mantener el mismo
        // comportamiento en MariaDB y SQLite. El filtro previo limita el lote.
        return $this->productosCatalogoCandidatos($codigo, $descripcion)
            ->filter(function (Producto $producto) use ($codigo, $descripcion): bool {
                if ($codigo !== '' && $codigo === $this->normalizarCodigo($producto->codigo)) {
                    return true;
                }

                return $descripcion !== ''
                    && $descripcion === $this->normalizarTexto($producto->descripcion);
            })
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function candidatosRequerimiento(
        Collection $detalles,
        string $codigo,
        string $descripcion
    ): Collection {
        return $detalles
            ->filter(fn (RequisicionDetalle $detalle): bool => $detalle->producto !== null)
            ->map(function (RequisicionDetalle $detalle) use ($codigo, $descripcion): array {
                return $this->candidato(
                    $detalle->producto,
                    $codigo,
                    $descripcion,
                    'REQUERIMIENTO',
                    $detalle
                );
            })
            ->filter(fn (array $candidato): bool => $candidato['puntaje'] >= 55)
            ->sortByDesc('puntaje')
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function candidatosCatalogo(
        string $codigo,
        string $descripcion,
        Collection $excluirIds
    ): Collection {
        return $this->productosCatalogoCandidatos($codigo, $descripcion)
            ->reject(fn (Producto $producto): bool => $excluirIds->contains($producto->id))
            ->map(fn (Producto $producto): array => $this->candidato(
                $producto,
                $codigo,
                $descripcion,
                'CATALOGO'
            ))
            ->filter(fn (array $candidato): bool => $candidato['puntaje'] >= 55)
            ->sortByDesc('puntaje')
            ->values();
    }

    /** @return Collection<int, Producto> */
    private function productosCatalogoCandidatos(string $codigo, string $descripcion): Collection
    {
        $tokens = collect(explode(' ', $descripcion))
            ->filter(fn (string $token): bool => mb_strlen($token) >= 4)
            ->take(4)
            ->values();

        $query = Producto::query()
            ->with('unidadMedida')
            ->where('estado', true);

        if ($codigo !== '' || $tokens->isNotEmpty()) {
            $query->where(function ($filtro) use ($codigo, $tokens): void {
                if ($codigo !== '') {
                    $filtro->orWhereRaw(
                        "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(codigo, '-', ''), '.', ''), '/', ''), '_', '')) LIKE ?",
                        ['%' . $codigo . '%']
                    );
                }

                foreach ($tokens as $token) {
                    $filtro->orWhere('descripcion', 'like', '%' . $token . '%');
                }
            });
        } else {
            return collect();
        }

        return $query->orderBy('codigo')->limit(60)->get();
    }

    /** @return array<string, mixed> */
    private function candidato(
        Producto $producto,
        string $codigo,
        string $descripcion,
        string $ambito,
        ?RequisicionDetalle $detalle = null
    ): array {
        $codigoProducto = $this->normalizarCodigo($producto->codigo);
        $descripcionProducto = $this->normalizarTexto($producto->descripcion);
        $puntajeDescripcion = 0.0;

        if ($descripcion !== '' && $descripcionProducto !== '') {
            similar_text($descripcion, $descripcionProducto, $puntajeDescripcion);
            $puntajeDescripcion = max(
                $puntajeDescripcion,
                $this->puntajeTokens($descripcion, $descripcionProducto)
            );
        }

        $puntajeCodigo = 0.0;
        if ($codigo !== '' && $codigoProducto !== '') {
            if (str_contains($codigoProducto, $codigo) || str_contains($codigo, $codigoProducto)) {
                $puntajeCodigo = min(mb_strlen($codigo), mb_strlen($codigoProducto)) >= 4 ? 88.0 : 60.0;
            }
        }

        $puntaje = round(max($puntajeDescripcion, $puntajeCodigo), 1);

        return [
            'producto_id' => $producto->id,
            'producto' => [
                'id' => $producto->id,
                'codigo' => $producto->codigo,
                'descripcion' => $producto->descripcion,
                'unidad' => $this->unidadVisible($producto),
                'label' => $producto->codigo . ' — ' . $producto->descripcion
                    . ($this->unidadVisible($producto) ? ' · ' . $this->unidadVisible($producto) : ''),
            ],
            'requisicion_detalle_id' => $detalle?->id,
            'tipo_vinculacion_propuesto' => $detalle ? 'SOLICITADO' : 'ADICIONAL',
            'ambito' => $ambito,
            'puntaje' => $puntaje,
            'codigo' => $producto->codigo,
            'descripcion' => $producto->descripcion,
            'unidad' => $this->unidadVisible($producto),
            'label' => $producto->codigo . ' — ' . $producto->descripcion
                . ($this->unidadVisible($producto) ? ' · ' . $this->unidadVisible($producto) : ''),
        ];
    }

    private function puntajeTokens(string $origen, string $candidato): float
    {
        $tokensOrigen = collect(explode(' ', $origen))->filter()->unique();
        $tokensCandidato = collect(explode(' ', $candidato))->filter()->unique();
        $union = $tokensOrigen->merge($tokensCandidato)->unique()->count();

        if ($union === 0) {
            return 0.0;
        }

        $interseccion = $tokensOrigen->intersect($tokensCandidato)->count();

        return ($interseccion / $union) * 100;
    }

    /** @return array<string, mixed> */
    private function resultadoAutomatico(
        Producto $producto,
        ?RequisicionDetalle $detalle,
        string $tipo,
        string $coincidencia
    ): array {
        return [
            'producto_id' => $producto->id,
            'producto' => [
                'id' => $producto->id,
                'codigo' => $producto->codigo,
                'descripcion' => $producto->descripcion,
                'unidad' => $this->unidadVisible($producto),
                'label' => $producto->codigo . ' — ' . $producto->descripcion
                    . ($this->unidadVisible($producto) ? ' · ' . $this->unidadVisible($producto) : ''),
            ],
            'requisicion_detalle_id' => $detalle?->id,
            'tipo_vinculacion' => $tipo,
            'vinculacion_origen' => 'AUTOMATICA',
            'coincidencia' => $coincidencia,
            'candidatos' => [],
        ];
    }

    private function unidadVisible(Producto $producto): ?string
    {
        $unidad = $producto->unidadMedida;

        return $unidad
            ? ($unidad->abreviatura ?? $unidad->codigo ?? $unidad->nombre)
            : null;
    }

    private function normalizarCodigo(string $valor): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower(trim($valor)))) ?: '';
    }

    private function normalizarTexto(string $valor): string
    {
        $valor = Str::ascii(mb_strtolower(trim($valor)));
        $valor = preg_replace('/[^a-z0-9]+/', ' ', $valor) ?: '';

        return trim(preg_replace('/\s+/', ' ', $valor) ?: $valor);
    }
}
