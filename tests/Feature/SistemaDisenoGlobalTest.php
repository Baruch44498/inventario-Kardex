<?php

namespace Tests\Feature;

use Tests\TestCase;

class SistemaDisenoGlobalTest extends TestCase
{
    public function test_layout_carga_la_base_visual_y_el_javascript_global(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('css/hidroil-design-system.css', $layout);
        $this->assertStringContainsString('js/hidroil-ui.js', $layout);
        $this->assertStringContainsString('x-ui.confirmation-modal', $layout);
        $this->assertStringNotContainsString('window.confirm', $layout);
    }

    public function test_no_quedan_confirmaciones_nativas_ni_eventos_inline_en_las_vistas(): void
    {
        $archivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($archivos as $archivo) {
            if (! $archivo->isFile() || $archivo->getExtension() !== 'php') {
                continue;
            }

            $contenido = file_get_contents($archivo->getPathname());
            $this->assertStringNotContainsString('window.confirm', $contenido, $archivo->getPathname());
            $this->assertStringNotContainsString('window.alert', $contenido, $archivo->getPathname());
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/', $contenido, $archivo->getPathname());
        }
    }

    public function test_los_componentes_globales_iniciales_existen(): void
    {
        foreach (
            [
                'page-header',
                'status-badge',
                'money',
                'module-card',
                'confirmation-modal',
                'planned-module',
            ] as $componente
        ) {
            $this->assertFileExists(
                resource_path("views/components/ui/{$componente}.blade.php")
            );
        }

        $this->assertFileExists(base_path('GUIA_VISUAL_HIDROIL.md'));
    }
}
