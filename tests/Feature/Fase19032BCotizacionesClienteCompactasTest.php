<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19032BCotizacionesClienteCompactasTest extends TestCase
{
    private function contenidoDetalle(): string
    {
        $parciales = collect([
            '_show_resumen_comercial',
            '_show_areas_materiales',
            '_show_presupuesto',
            '_show_versiones_documentos',
        ])->map(fn (string $parcial): string => file_get_contents(
                resource_path("views/cotizaciones_cliente/partials/{$parcial}.blade.php")
            ))->implode("\n");

        return file_get_contents(resource_path('views/cotizaciones_cliente/show.blade.php'))
            .$parciales;
    }

    public function test_detalle_se_divide_en_cuatro_secciones_explicitas(): void
    {
        $vista = file_get_contents(resource_path('views/cotizaciones_cliente/show.blade.php'));

        foreach ([
            '_show_resumen_comercial',
            '_show_areas_materiales',
            '_show_presupuesto',
            '_show_versiones_documentos',
        ] as $parcial) {
            $this->assertFileExists(resource_path("views/cotizaciones_cliente/partials/{$parcial}.blade.php"));
            $this->assertStringContainsString("cotizaciones_cliente.partials.{$parcial}", $vista);
        }

        $this->assertLessThan(130, count(file(resource_path('views/cotizaciones_cliente/show.blade.php'))));
        $this->assertSame(5, substr_count($this->contenidoDetalle(), '<table'));
    }

    public function test_pestanas_accesibles_usan_paneles_existentes_sin_reagrupar_el_dom(): void
    {
        $vista = file_get_contents(resource_path('views/cotizaciones_cliente/show.blade.php'));
        $script = file_get_contents(public_path('js/commercial-quote-detail-tabs.js'));
        $css = file_get_contents(public_path('css/hidroil/ordenes.css'));

        $this->assertStringContainsString('data-commercial-quote-tabs-root', $vista);
        $this->assertStringContainsString('role="tablist"', $vista);
        $this->assertSame(4, substr_count($vista, 'data-commercial-quote-tab="'));
        $this->assertSame(4, substr_count($vista, 'data-commercial-quote-panel="'));
        $this->assertDoesNotMatchRegularExpression('/data-commercial-quote-panel="[^"]+"[^>]*hidden/', $vista);
        $this->assertStringContainsString("querySelector('[data-commercial-quote-tabs-root]')", $script);
        $this->assertStringContainsString("root.classList.add('commercial-quote-detail--tabbed')", $script);
        $this->assertStringContainsString("window.addEventListener('hashchange'", $script);
        $this->assertStringContainsString('.commercial-quote-detail:not(.commercial-quote-detail--tabbed) .commercial-quote-detail-tabs', $css);
        $this->assertStringNotContainsString('document.createElement', $script);
        $this->assertStringNotContainsString('append(', $script);
    }

    public function test_presupuesto_interno_conserva_flujos_y_tablas_compactas(): void
    {
        $presupuesto = file_get_contents(resource_path('views/cotizaciones_cliente/presupuesto.blade.php'));

        foreach ([
            "route('cotizaciones-cliente.excel.create'",
            "route('cotizaciones-cliente.excel.download'",
            "route('plantillas-costeo.importaciones.create'",
            "route('plantillas-costeo.index'",
            "route('cotizacion-componentes.plantillas.aplicar'",
            "route('cotizacion-componentes.plantillas.guardar'",
            "route('cotizaciones-cliente.presupuesto.sincronizar'",
        ] as $flujo) {
            $this->assertStringContainsString($flujo, $presupuesto);
        }

        $this->assertSame(3, substr_count($presupuesto, 'budget-review-panel'));
        $this->assertSame(3, substr_count($presupuesto, 'budget-review-table-wrap'));
        $this->assertSame(3, substr_count($presupuesto, 'data-table budget-review-table'));
    }

    public function test_conversion_documentos_permisos_y_precision_visual_se_conservan(): void
    {
        $contenido = $this->contenidoDetalle();
        $formulario = file_get_contents(resource_path('views/cotizaciones_cliente/_presupuesto_form.blade.php'));

        foreach ([
            "puede('proformas.cotizar')",
            "route('cotizaciones-cliente.presupuesto.show'",
            "route('cotizaciones-cliente.convertir-orden'",
            "route('cotizaciones-cliente.cerrar'",
            "route('cotizaciones-cliente.anular'",
            "route('cotizaciones-cliente.version'",
            '@csrf',
            "number_format((float) \$cotizacion->tipo_cambio, 2",
        ] as $regla) {
            $this->assertStringContainsString($regla, $contenido);
        }

        $this->assertDoesNotMatchRegularExpression('/number_format\([^\n]*,\s*[3-9]\d*/', $contenido.$formulario);
        $this->assertStringContainsString('step="0.0001"', $formulario);
    }
}
