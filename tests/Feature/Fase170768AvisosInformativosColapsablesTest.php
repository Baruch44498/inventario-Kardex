<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase170768AvisosInformativosColapsablesTest extends TestCase
{
    public function test_existe_componente_global_compacto_y_accesible(): void
    {
        $componente = file_get_contents(resource_path('views/components/ui/collapsible-notice.blade.php'));

        $this->assertStringContainsString('<details', $componente);
        $this->assertStringContainsString('data-collapsible-notice', $componente);
        $this->assertStringContainsString('ui-collapsible-notice__trigger', $componente);
        $this->assertStringContainsString('aria-label=', $componente);
        $this->assertStringContainsString('sr-only', $componente);
    }

    public function test_nota_salida_reemplaza_ayudas_extensas_por_iconos_colapsables(): void
    {
        $vista = file_get_contents(resource_path('views/notas_salida/create.blade.php'));

        $this->assertStringContainsString('ui-collapsible-notice-cluster', $vista);
        $this->assertStringContainsString('<x-ui.collapsible-notice', $vista);
        $this->assertStringContainsString('Entrega guiada por la orden', $vista);
        $this->assertStringContainsString('Los materiales previstos de la orden ya están cargados en la tabla.', $vista);
    }

    public function test_errores_y_advertencias_criticas_siguen_visibles(): void
    {
        $vista = file_get_contents(resource_path('views/notas_salida/create.blade.php'));

        $this->assertStringContainsString('notice notice--warning notice--block', $vista);
        $this->assertStringContainsString('notice notice--danger notice--block', $vista);
        $this->assertStringContainsString('Pendientes sin stock físico', $vista);
    }

    public function test_patron_se_reutiliza_en_modulos_distintos(): void
    {
        $vistas = [
            'views/notas_salida/index.blade.php',
            'views/notas_ingreso/index.blade.php',
            'views/alertas/index.blade.php',
            'views/cliente_direcciones/_form.blade.php',
            'views/ordenes_operacion/create.blade.php',
            'views/ordenes_operacion/index.blade.php',
            'views/ordenes_operacion/show.blade.php',
            'views/cotizaciones_cliente/_form.blade.php',
        ];

        foreach ($vistas as $vista) {
            $this->assertStringContainsString(
                '<x-ui.collapsible-notice',
                file_get_contents(resource_path($vista)),
                "La vista {$vista} debe usar el patrón global colapsable."
            );
        }
    }

    public function test_css_mantiene_solo_el_icono_y_abre_panel_flotante(): void
    {
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('.ui-collapsible-notice__trigger', $css);
        $this->assertStringContainsString('width: 34px;', $css);
        $this->assertStringContainsString('height: 34px;', $css);
        $this->assertStringContainsString('.ui-collapsible-notice__panel', $css);
        $this->assertStringContainsString('position: absolute;', $css);
        $this->assertStringContainsString('position: fixed;', $css);
    }

    public function test_interaccion_global_cierra_ayudas_al_salir_o_presionar_escape(): void
    {
        $js = file_get_contents(public_path('js/hidroil-ui.js'));

        $this->assertStringContainsString("querySelectorAll('[data-collapsible-notice]')", $js);
        $this->assertStringContainsString("event.key !== 'Escape'", $js);
        $this->assertStringContainsString('!notice.contains(event.target)', $js);
    }

    public function test_cotizacion_conserva_selectores_dinamicos_del_detalle_comercial(): void
    {
        $vista = file_get_contents(resource_path('views/cotizaciones_cliente/_form.blade.php'));

        $this->assertStringContainsString('data-commercial-detail-note', $vista);
        $this->assertStringContainsString('data-commercial-detail-note-title', $vista);
        $this->assertStringContainsString('data-commercial-detail-note-text', $vista);
    }
}
