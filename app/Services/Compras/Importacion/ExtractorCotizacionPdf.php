<?php

namespace App\Services\Compras\Importacion;

use RuntimeException;

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
        $texto = trim($parser->parseFile($rutaAbsoluta)->getText());

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
}
