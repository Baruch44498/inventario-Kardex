<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19033ConsolidacionCssTest extends TestCase
{
    /** @return list<string> */
    private function hojas(string $vista): array
    {
        preg_match_all(
            "/asset\\('css\\/([^']+)'\\)/",
            file_get_contents(resource_path("views/{$vista}.blade.php")),
            $coincidencias
        );

        return array_map(static fn (string $nombre): string => "css/{$nombre}", $coincidencias[1]);
    }

    public function test_las_hojas_directas_de_los_tres_layouts_existen_y_respetan_la_cascada(): void
    {
        $principal = [
            'css/hidroil-admin.css',
            'css/hidroil/compras.css',
            'css/hidroil/inventario.css',
            'css/hidroil/ordenes.css',
            'css/hidroil/base.css',
            'css/hidroil/components.css',
            'css/hidroil/responsive.css',
        ];

        $this->assertSame($principal, $this->hojas('layouts/app'));
        $this->assertSame(
            ['css/hidroil-admin.css', 'css/hidroil/base.css'],
            $this->hojas('auth/login')
        );
        $this->assertSame(
            ['css/hidroil-admin.css', 'css/hidroil/base.css', 'css/hidroil/components.css', 'css/hidroil/responsive.css'],
            $this->hojas('errors/404')
        );

        foreach ($principal as $archivo) {
            $this->assertFileExists(public_path($archivo), $archivo);
        }
    }

    public function test_tokens_unicos_y_componentes_migrados_se_encuentran_en_hojas_cargadas(): void
    {
        $css = implode("\n", array_map(
            static fn (string $archivo): string => file_get_contents(public_path($archivo)),
            $this->hojas('layouts/app')
        ));
        $base = file_get_contents(public_path('css/hidroil/base.css'));
        $componentes = file_get_contents(public_path('css/hidroil/components.css'));
        $responsive = file_get_contents(public_path('css/hidroil/responsive.css'));

        $this->assertSame(1, substr_count($css, ':root {'));
        $this->assertStringContainsString('--color-primary: var(--brand-primary);', $base);
        foreach (['.button {', '.badge {', '.panel,', '.data-table {', '.notice {', '.ui-status-badge {', '.ui-collapsible-notice__panel {'] as $selector) {
            $this->assertStringContainsString($selector, $componentes);
        }
        $this->assertStringContainsString('@media (max-width: 680px)', $responsive);
        $this->assertStringContainsString('position: fixed;', $responsive);
        $this->assertStringNotContainsString("asset('css/hidroil-design-system.css')", file_get_contents(resource_path('views/layouts/app.blade.php')));
    }

    public function test_tabla_de_detalle_mantiene_sus_celdas_compactas_tras_mover_la_tabla_global(): void
    {
        $compras = file_get_contents(public_path('css/hidroil/compras.css'));
        $tabla = file_get_contents(resource_path('views/proformas/show.blade.php'));

        $this->assertStringContainsString('data-table data-table--detail', $tabla);
        $this->assertStringContainsString('.data-table.data-table--detail td span {', $compras);
        $this->assertStringContainsString('white-space: normal;', $compras);
    }
}
