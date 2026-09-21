<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19032CClientesProveedoresCatalogosTest extends TestCase
{
    private function contenidoParciales(string $directorio, array $parciales): string
    {
        return collect($parciales)->map(fn (string $parcial): string => file_get_contents(
            resource_path("views/{$directorio}/partials/{$parcial}.blade.php")
        ))->implode("\n");
    }

    public function test_formulario_de_cliente_esta_dividido_y_sin_javascript_inline(): void
    {
        $vista = file_get_contents(resource_path('views/clientes/_form.blade.php'));
        $parciales = [
            '_form_identificacion',
            '_form_contacto',
            '_form_acciones',
        ];

        foreach ($parciales as $parcial) {
            $this->assertFileExists(resource_path("views/clientes/partials/{$parcial}.blade.php"));
            $this->assertStringContainsString("clientes.partials.{$parcial}", $vista);
        }

        $this->assertLessThan(60, count(file(resource_path('views/clientes/_form.blade.php'))));
        $this->assertStringContainsString("asset('js/client-form.js')", $vista);
        $this->assertStringNotContainsString('<script>', $vista);
    }

    public function test_javascript_del_cliente_conserva_reglas_de_documento_y_contacto(): void
    {
        $script = file_get_contents(public_path('js/client-form.js'));
        $contenido = $this->contenidoParciales('clientes', [
            '_form_identificacion',
            '_form_contacto',
            '_form_acciones',
        ]);

        foreach ([
            '[data-client-document-type]',
            '[data-client-document-number]',
            '[data-client-ruc-field]',
            '[data-client-person-field]',
            '[data-client-dni-field]',
            'SIN_DOCUMENTO',
            'PÚBLICO GENERAL',
            "type?.addEventListener(\n            'change'",
        ] as $regla) {
            $this->assertStringContainsString($regla, $script);
        }

        foreach ([
            'name="tipo_cliente_id"',
            'name="tipo_documento"',
            'name="numero_documento"',
            'name="contacto"',
            'name="telefono"',
            'name="correo"',
            'name="estado"',
        ] as $campo) {
            $this->assertStringContainsString($campo, $contenido);
        }
    }

    public function test_proveedor_se_divide_en_datos_productos_documentos_e_historial(): void
    {
        $vista = file_get_contents(resource_path('views/proveedores/show.blade.php'));
        $parciales = [
            '_show_datos',
            '_show_productos_precios',
            '_show_documentos',
            '_show_historial',
        ];

        foreach ($parciales as $parcial) {
            $this->assertFileExists(resource_path("views/proveedores/partials/{$parcial}.blade.php"));
            $this->assertStringContainsString("proveedores.partials.{$parcial}", $vista);
        }

        $this->assertLessThan(110, count(file(resource_path('views/proveedores/show.blade.php'))));
        $this->assertSame(4, substr_count($vista, 'data-supplier-detail-tab="'));
        $this->assertSame(4, substr_count($vista, 'data-supplier-detail-panel="'));
        $this->assertDoesNotMatchRegularExpression('/data-supplier-detail-panel="[^"]+"[^>]*hidden/', $vista);
    }

    public function test_pestanas_de_proveedor_conservan_paginacion_rutas_y_tablas(): void
    {
        $script = file_get_contents(public_path('js/supplier-detail-tabs.js'));
        $contenido = file_get_contents(resource_path('views/proveedores/show.blade.php'))
            .$this->contenidoParciales('proveedores', [
                '_show_datos',
                '_show_productos_precios',
                '_show_documentos',
                '_show_historial',
            ]);

        foreach ([
            "route('proveedores.index'",
            "route('proveedores.edit'",
            "route('cotizaciones-proveedor.create'",
            "route('cotizaciones-proveedor.show'",
            "route('historial-precios.index'",
            'Datos comerciales',
            'Precios ofrecidos',
            'Cotizaciones del proveedor',
            'Historial comercial',
        ] as $regla) {
            $this->assertStringContainsString($regla, $contenido);
        }

        $this->assertSame(2, substr_count($contenido, '<table'));
        $this->assertStringContainsString("query.has('precios')", $script);
        $this->assertStringContainsString("query.has('cotizaciones')", $script);
        $this->assertStringContainsString("window.addEventListener('hashchange'", $script);
        $this->assertStringNotContainsString('document.createElement', $script);
    }

    public function test_catalogos_pequenos_siguen_sin_pestanas_innecesarias(): void
    {
        $archivos = [
            'views/vehiculos/_form.blade.php',
            'views/vehiculos/show.blade.php',
            'views/repisas/index.blade.php',
            'views/empleados/index.blade.php',
            'views/usuarios/index.blade.php',
        ];

        foreach ($archivos as $archivo) {
            $contenido = file_get_contents(resource_path($archivo));
            $this->assertStringNotContainsString('role="tablist"', $contenido, $archivo);
            $this->assertLessThan(320, count(file(resource_path($archivo))), $archivo);
        }

        $repisas = file_get_contents(resource_path('views/repisas/index.blade.php'));
        $this->assertStringContainsString('class="data-table data-table--actions data-table--responsive"', $repisas);
        $this->assertStringNotContainsString('data-table--responsive}', $repisas);
    }
}
