<?php

namespace App\Services\Compras\Importacion;

use RuntimeException;

class ExtractorCotizacionExcel
{
    public function extraer(string $rutaAbsoluta): array
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new RuntimeException(
                'Falta PhpSpreadsheet. Ejecuta composer update phpoffice/phpspreadsheet smalot/pdfparser antes de usar la importación.'
            );
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($rutaAbsoluta);
        $hoja = $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray(null, true, true, false);

        return [
            'tipo' => 'EXCEL',
            'filas' => array_slice($filas, 0, 350),
            'texto' => collect($filas)
                ->take(350)
                ->map(fn (array $fila): string => collect($fila)
                    ->map(fn ($valor): string => trim((string) $valor))
                    ->filter()
                    ->implode(' | '))
                ->filter()
                ->implode("\n"),
        ];
    }
}
