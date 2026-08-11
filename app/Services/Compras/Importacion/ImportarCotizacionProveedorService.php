<?php

namespace App\Services\Compras\Importacion;

use App\Models\Requisicion;
use RuntimeException;

class ImportarCotizacionProveedorService
{
    public function __construct(
        private ExtractorCotizacionExcel $excel,
        private ExtractorCotizacionPdf $pdf,
        private InterpretarCotizacionImportada $interpretador,
    ) {}

    public function procesar(string $rutaAbsoluta, string $extension, Requisicion $requisicion): array
    {
        $extension = mb_strtolower($extension);

        $extraido = match ($extension) {
            'xlsx', 'xls', 'csv' => $this->excel->extraer($rutaAbsoluta),
            'pdf' => $this->pdf->extraer($rutaAbsoluta),
            default => throw new RuntimeException('Formato no compatible. Usa Excel (.xlsx, .xls, .csv) o PDF digital.'),
        };

        return $this->interpretador->interpretar($extraido, $requisicion);
    }
}
