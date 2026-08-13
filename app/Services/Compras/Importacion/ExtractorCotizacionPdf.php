<?php

namespace App\Services\Compras\Importacion;

use RuntimeException;
use Throwable;

class ExtractorCotizacionPdf
{
    public function extraer(string $rutaAbsoluta): array
    {
        if (! class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new RuntimeException(
                'Falta PdfParser. Ejecuta composer update phpoffice/phpspreadsheet smalot/pdfparser antes de usar la importación.'
            );
        }

        $parser = new \Smalot\PdfParser\Parser();
        $documento = $parser->parseFile($rutaAbsoluta);
        $textoPlano = trim($documento->getText());
        $textoPosicional = $this->textoOrdenadoPorPosicion($documento);

        // getText() conserva el contenido, pero en tablas puede devolver las
        // celdas por orden interno del PDF y no por filas visuales. Primero se
        // entrega la reconstrucción X/Y al intérprete y se adjunta el texto
        // plano como respaldo para cabeceras o fragmentos sin coordenadas.
        $texto = trim(implode("\n", array_filter([
            $textoPosicional,
            $textoPlano,
        ], fn(string $valor): bool => trim($valor) !== '')));

        if ($texto === '') {
            throw new RuntimeException(
                'El PDF no contiene texto digital utilizable. Este bloque no aplica OCR a documentos escaneados; registra la cotización manualmente o usa un PDF digital.'
            );
        }

        return [
            'tipo' => 'PDF',
            'texto' => $texto,
        ];
    }

    /**
     * Reconstruye las filas según las coordenadas que Smalot obtiene de cada
     * fragmento de texto. No invoca programas externos, por lo que funciona
     * también en XAMPP/Windows con la dependencia Composer ya instalada.
     */
    private function textoOrdenadoPorPosicion(object $documento): string
    {
        $paginas = [];

        try {
            $paginasDocumento = $documento->getPages();
        } catch (Throwable) {
            return '';
        }

        foreach ($paginasDocumento as $pagina) {
            try {
                $datos = $pagina->getDataTm();
            } catch (Throwable) {
                continue;
            }

            $fragmentos = [];
            foreach ($datos as $dato) {
                if (! is_array($dato) || ! is_array($dato[0] ?? null)) {
                    continue;
                }

                $matriz = $dato[0];
                $texto = trim((string) ($dato[1] ?? ''));
                $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?: $texto);

                if (
                    $texto === ''
                    || ! isset($matriz[4], $matriz[5])
                    || ! is_numeric($matriz[4])
                    || ! is_numeric($matriz[5])
                ) {
                    continue;
                }

                $fragmentos[] = [
                    'x' => (float) $matriz[4],
                    'y' => (float) $matriz[5],
                    'texto' => $texto,
                ];
            }

            if ($fragmentos === []) {
                continue;
            }

            usort($fragmentos, function (array $izquierda, array $derecha): int {
                $comparacionVertical = $derecha['y'] <=> $izquierda['y'];

                return $comparacionVertical !== 0
                    ? $comparacionVertical
                    : ($izquierda['x'] <=> $derecha['x']);
            });

            $filas = [];
            foreach ($fragmentos as $fragmento) {
                $indiceFila = array_key_last($filas);

                // Los fragmentos ya están ordenados de arriba hacia abajo.
                // Por eso solo la última fila puede ser la fila visual actual.
                if (
                    $indiceFila !== null
                    && abs($filas[$indiceFila]['y'] - $fragmento['y']) > 2.0
                ) {
                    $indiceFila = null;
                }

                if ($indiceFila === null) {
                    $filas[] = [
                        'y' => $fragmento['y'],
                        'fragmentos' => [$fragmento],
                    ];
                    continue;
                }

                $filas[$indiceFila]['fragmentos'][] = $fragmento;
            }

            usort($filas, fn(array $izquierda, array $derecha): int => $derecha['y'] <=> $izquierda['y']);

            $lineasPagina = [];
            foreach ($filas as $fila) {
                usort(
                    $fila['fragmentos'],
                    fn(array $izquierda, array $derecha): int => $izquierda['x'] <=> $derecha['x']
                );

                $textosFila = array_map(
                    fn(array $fragmento): string => trim((string) $fragmento['texto']),
                    $fila['fragmentos']
                );
                $linea = implode(' ', array_filter(
                    $textosFila,
                    fn(string $texto): bool => $texto !== ''
                ));
                $linea = trim(preg_replace('/\s+/u', ' ', $linea) ?: $linea);

                if ($linea !== '') {
                    $lineasPagina[] = $linea;
                }
            }

            if ($lineasPagina !== []) {
                $paginas[] = implode("\n", $lineasPagina);
            }
        }

        return trim(implode("\n", $paginas));
    }
}
