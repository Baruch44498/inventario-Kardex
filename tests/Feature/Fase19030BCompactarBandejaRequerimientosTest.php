<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19030BCompactarBandejaRequerimientosTest extends TestCase
{
    public function test_la_bandeja_deja_seis_columnas_operativas_visibles(): void
    {
        $vista = file_get_contents(resource_path('views/requerimientos_compra/index.blade.php'));

        $this->assertSame(6, preg_match_all('/<th(?:\s|>)/', $vista));
        $this->assertStringContainsString('>Requerimiento</th>', $vista);
        $this->assertStringContainsString('>Fecha</th>', $vista);
        $this->assertStringContainsString('>Prioridad</th>', $vista);
        $this->assertStringContainsString('>Gestión</th>', $vista);
        $this->assertStringContainsString('>Avance</th>', $vista);
        $this->assertStringContainsString('>Acción</th>', $vista);
        $this->assertStringNotContainsString('<th>Solicitante</th>', $vista);
        $this->assertStringNotContainsString('<th>Responsable</th>', $vista);
        $this->assertStringNotContainsString('<th>Productos</th>', $vista);
    }

    public function test_los_datos_secundarios_quedan_en_una_fila_expandible(): void
    {
        $vista = file_get_contents(resource_path('views/requerimientos_compra/index.blade.php'));

        $this->assertStringContainsString('<x-ui.table-details-toggle', $vista);
        $this->assertStringContainsString('<x-ui.table-row-details', $vista);
        $this->assertStringContainsString('purchase-requirement-list-details', $vista);

        foreach (['Descripción', 'Origen', 'Solicitante', 'Responsable', 'Productos'] as $dato) {
            $this->assertStringContainsString("<dt>{$dato}</dt>", $vista);
        }
    }

    public function test_conserva_filtros_acciones_paginacion_y_calcula_el_avance(): void
    {
        $vista = file_get_contents(resource_path('views/requerimientos_compra/index.blade.php'));
        $controlador = file_get_contents(app_path('Http/Controllers/RequerimientoCompraController.php'));

        foreach (['q', 'estado', 'origen', 'abastecimiento'] as $filtro) {
            $this->assertStringContainsString("name=\"{$filtro}\"", $vista);
        }

        $this->assertStringContainsString('siguiente_accion', $vista);
        $this->assertStringContainsString('<x-ui.pagination :paginator="$requerimientos"', $vista);
        $this->assertStringContainsString(
            "'detalles:id,requisicion_id,cantidad_solicitada,cantidad_atendida'",
            $controlador
        );
        $this->assertStringContainsString('$requerimiento->detalles->avg(', $controlador);
        $this->assertStringContainsString("'avance_abastecimiento'", $controlador);
        $this->assertStringContainsString('% recibido', $vista);
    }

    public function test_en_movil_la_tabla_se_convierte_en_tarjetas_sin_ancho_forzado(): void
    {
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('.data-table--responsive.purchase-requirement-list-table {', $css);
        $this->assertStringContainsString('min-width: 0 !important;', $css);
        $this->assertStringContainsString('.purchase-requirement-list-table .purchase-requirement-list-row {', $css);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr));', $css);
        $this->assertStringContainsString('.purchase-requirement-list-table .table-details-row:not([hidden]) {', $css);
        $this->assertStringNotContainsString(".purchase-requirement-list-table {\n    min-width: 1120px;", $css);
    }
}
