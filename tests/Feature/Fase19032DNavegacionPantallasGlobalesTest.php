<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19032DNavegacionPantallasGlobalesTest extends TestCase
{
    private function contenidoSidebar(): string
    {
        $parciales = collect([
            '_general',
            '_comercial',
            '_compras',
            '_almacen',
            '_produccion',
            '_contabilidad',
            '_administracion',
        ])->map(fn (string $parcial): string => file_get_contents(
            resource_path("views/layouts/partials/sidebar/{$parcial}.blade.php")
        ))->implode("\n");

        return file_get_contents(resource_path('views/layouts/partials/sidebar.blade.php'))
            .$parciales;
    }

    private function contenidoDashboard(): string
    {
        $parciales = collect([
            '_modo_administrador',
            '_modo_almacen',
            '_modo_ordenes',
            '_modo_contabilidad',
            '_modo_sin_configuracion',
        ])->map(fn (string $parcial): string => file_get_contents(
            resource_path("views/dashboard/partials/{$parcial}.blade.php")
        ))->implode("\n");

        return file_get_contents(resource_path('views/dashboard/index.blade.php'))
            .$parciales;
    }

    public function test_sidebar_se_divide_por_grupos_sin_perder_permisos_ni_rutas(): void
    {
        $vista = file_get_contents(resource_path('views/layouts/partials/sidebar.blade.php'));
        $parciales = [
            '_general',
            '_comercial',
            '_compras',
            '_almacen',
            '_produccion',
            '_contabilidad',
            '_administracion',
        ];

        foreach ($parciales as $parcial) {
            $this->assertFileExists(resource_path("views/layouts/partials/sidebar/{$parcial}.blade.php"));
            $this->assertStringContainsString("layouts.partials.sidebar.{$parcial}", $vista);
        }

        $contenido = $this->contenidoSidebar();
        $this->assertLessThan(120, count(file(resource_path('views/layouts/partials/sidebar.blade.php'))));
        $this->assertSame(6, substr_count($contenido, 'data-sidebar-group="'));

        foreach ([
            "puedeAlguno(",
            "puede('clientes.gestionar')",
            "puede('compras.gestionar')",
            "puede('inventario.ver')",
            "puede('produccion.ver')",
            "puede('contabilidad.ver')",
            "puede('usuarios.gestionar')",
            "route('dashboard')",
            "route('ordenes-operacion.index'",
            "route('facturas-proveedor.index'",
        ] as $regla) {
            $this->assertStringContainsString($regla, $contenido);
        }
    }

    public function test_dashboard_se_divide_por_perfil_sin_aumentar_tarjetas_visibles(): void
    {
        $vista = file_get_contents(resource_path('views/dashboard/index.blade.php'));
        $parciales = [
            '_modo_administrador',
            '_modo_almacen',
            '_modo_ordenes',
            '_modo_contabilidad',
            '_modo_sin_configuracion',
        ];

        foreach ($parciales as $parcial) {
            $this->assertFileExists(resource_path("views/dashboard/partials/{$parcial}.blade.php"));
            $this->assertStringContainsString("dashboard.partials.{$parcial}", $vista);
        }

        $contenido = $this->contenidoDashboard();
        $this->assertLessThan(70, count(file(resource_path('views/dashboard/index.blade.php'))));
        $this->assertSame(27, substr_count($contenido, 'class="role-quick-card"'));
        $this->assertSame(6, substr_count($contenido, 'panel admin-area-card'));
        $this->assertSame(2, substr_count($contenido, '<table'));
        $this->assertStringContainsString("dashboard._bandeja_operativa", $contenido);
    }

    public function test_login_carga_script_externo_y_conserva_bloqueo_y_accesibilidad(): void
    {
        $vista = file_get_contents(resource_path('views/auth/login.blade.php'));
        $script = file_get_contents(public_path('js/login.js'));

        $this->assertLessThan(300, count(file(resource_path('views/auth/login.blade.php'))));
        $this->assertStringContainsString("asset('js/login.js')", $vista);
        $this->assertStringNotContainsString('<script>', $vista);

        foreach ([
            '[data-password-toggle]',
            '[data-login-form]',
            '[data-login-submit]',
            '[data-login-lockout]',
            '[data-login-countdown]',
            '[data-login-lockable]',
            "setInterval(updateCountdown, 1000)",
            "clearInterval(timer)",
        ] as $regla) {
            $this->assertStringContainsString($regla, $script);
        }
    }

    public function test_estados_vacios_y_siguiente_accion_reutilizan_componentes_globales(): void
    {
        $empty = file_get_contents(resource_path('views/components/ui/empty-table.blade.php'));
        $next = file_get_contents(resource_path('views/components/ui/next-action.blade.php'));
        $dashboard = $this->contenidoDashboard()
            .file_get_contents(resource_path('views/dashboard/_bandeja_operativa.blade.php'));
        $encabezado = file_get_contents(resource_path('views/requerimientos_compra/partials/_show_encabezado.blade.php'));

        $this->assertStringContainsString("\$attributes->class(['empty-table-state'])", $empty);
        $this->assertSame(3, substr_count($dashboard, '<x-ui.empty-table'));
        $this->assertStringContainsString('<x-ui.next-action', $encabezado);
        $this->assertStringContainsString('Siguiente acción: {{ $title }}', $next);
        $this->assertStringContainsString('role="status"', $next);
    }
}
