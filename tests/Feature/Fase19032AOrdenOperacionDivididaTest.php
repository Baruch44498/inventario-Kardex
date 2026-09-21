<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19032AOrdenOperacionDivididaTest extends TestCase
{
    private function contenidoParciales(): string
    {
        return collect([
            '_show_resumen',
            '_show_ejecucion',
            '_show_materiales',
            '_show_reservas',
            '_show_herramientas',
            '_show_abastecimiento',
        ])->map(fn (string $parcial): string => file_get_contents(
            resource_path("views/ordenes_operacion/partials/{$parcial}.blade.php")
        ))->implode("\n");
    }

    public function test_detalle_principal_se_divide_en_seis_parciales_semanticos(): void
    {
        $vista = file_get_contents(resource_path('views/ordenes_operacion/show.blade.php'));

        foreach ([
            '_show_resumen',
            '_show_ejecucion',
            '_show_materiales',
            '_show_reservas',
            '_show_herramientas',
            '_show_abastecimiento',
        ] as $parcial) {
            $this->assertFileExists(resource_path("views/ordenes_operacion/partials/{$parcial}.blade.php"));
            $this->assertStringContainsString("ordenes_operacion.partials.{$parcial}", $vista);
        }

        $this->assertLessThan(320, count(file(resource_path('views/ordenes_operacion/show.blade.php'))));
        $this->assertSame(8, substr_count($this->contenidoParciales(), '<table'));
    }

    public function test_pestanas_y_paneles_existen_en_blade_sin_reagrupar_el_dom(): void
    {
        $vista = file_get_contents(resource_path('views/ordenes_operacion/show.blade.php'));
        $script = file_get_contents(public_path('js/operation-detail-tabs.js'));
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('data-operation-tabs-root', $vista);
        $this->assertStringContainsString('role="tablist"', $vista);
        $this->assertSame(6, substr_count($vista, 'data-operation-tab="'));
        $this->assertSame(6, substr_count($vista, 'data-operation-panel="'));
        $this->assertDoesNotMatchRegularExpression('/data-operation-panel="[^"]+"[^>]*hidden/', $vista);
        $this->assertStringContainsString("querySelector('[data-operation-tabs-root]')", $script);
        $this->assertStringContainsString("querySelectorAll('[data-operation-panel]')", $script);
        $this->assertStringContainsString("root.classList.add('operation-page--tabbed')", $script);
        $this->assertStringContainsString('.operation-page--show:not(.operation-page--tabbed) .operation-detail-tabs', $css);
        $this->assertStringNotContainsString('document.createElement', $script);
        $this->assertStringNotContainsString('directChildren', $script);
        $this->assertStringNotContainsString('panel.append(node)', $script);
    }

    public function test_hashes_existentes_abren_su_panel_explicito(): void
    {
        $contenido = file_get_contents(resource_path('views/ordenes_operacion/show.blade.php'))
            .$this->contenidoParciales();
        $script = file_get_contents(public_path('js/operation-detail-tabs.js'));

        foreach ([
            'avance-operativo',
            'costos-directos',
            'materiales-requeridos',
            'comparacion-materiales',
            'reservas-materiales',
            'herramientas-en-uso',
            'requerimientos-compra',
            'abastecimiento',
        ] as $ancla) {
            $this->assertStringContainsString("id=\"{$ancla}\"", $contenido);
            $this->assertStringContainsString("'{$ancla}'", $script);
        }

        $this->assertStringContainsString("window.addEventListener('hashchange'", $script);
    }

    public function test_permisos_estados_costos_y_acciones_se_conservan(): void
    {
        $contenido = file_get_contents(resource_path('views/ordenes_operacion/show.blade.php'))
            .$this->contenidoParciales();

        foreach ([
            "puede('ordenes.gestionar_estado')",
            "puede('ordenes.ver_costos')",
            "puede('ordenes.gestionar_costos')",
            "puede('salidas.registrar')",
            "route('ordenes-operacion.iniciar'",
            "route('ordenes-operacion.cerrar'",
            "route('ordenes-operacion.avances.store'",
            "route('ordenes-operacion.costos-directos.store'",
            "route('ordenes-operacion.materiales-requeridos.store'",
            'Costo real total acumulado',
            'Utilidad real',
            'Margen real sobre venta neta',
        ] as $regla) {
            $this->assertStringContainsString($regla, $contenido);
        }
    }

    public function test_modal_de_anulacion_y_navegacion_no_dejan_javascript_inline(): void
    {
        $vista = file_get_contents(resource_path('views/ordenes_operacion/show.blade.php'));
        $modal = file_get_contents(public_path('js/operation-cancel-modal.js'));

        $this->assertStringNotContainsString('<script>', $vista);
        $this->assertStringContainsString("asset('js/operation-detail-tabs.js')", $vista);
        $this->assertStringContainsString("asset('js/operation-cancel-modal.js')", $vista);
        $this->assertStringContainsString('[data-order-cancel-modal]', $modal);
        $this->assertStringContainsString("event.key === 'Escape'", $modal);
        $this->assertStringContainsString("document.body.classList.add('modal-open')", $modal);
    }
}
