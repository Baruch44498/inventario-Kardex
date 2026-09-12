<?php

namespace Tests\Feature;

use Tests\TestCase;

class Bloque1RecorteNavegacionRolesTest extends TestCase
{
    public function test_sidebar_expone_solo_las_areas_del_recorte(): void
    {
        $sidebar = file_get_contents(resource_path('views/layouts/partials/sidebar.blade.php'));

        foreach (['Ventas', 'Compras y proveedores', 'Almacén', 'Contabilidad', 'Administración del sistema'] as $area) {
            $this->assertStringContainsString($area, $sidebar);
        }

        foreach ([
            'Control de planta',
            'Órdenes OM, OS y OP',
            'Plantillas de costeo',
            'Proformas de venta directa',
            'Cuentas por cobrar',
            '>Auditoría<',
        ] as $moduloFueraDelRecorte) {
            $this->assertStringNotContainsString($moduloFueraDelRecorte, $sidebar);
        }
    }

    public function test_dashboard_no_consulta_ordenes_productivas(): void
    {
        $controlador = file_get_contents(app_path('Http/Controllers/DashboardController.php'));
        $vista = file_get_contents(resource_path('views/dashboard/index.blade.php'));

        $this->assertStringNotContainsString('OrdenOperacion', $controlador);
        $this->assertStringNotContainsString("\$modo === 'ordenes'", $vista);
        $this->assertStringContainsString("\$modo === 'comercial'", $vista);

        foreach (['Control de planta', 'Órdenes OM, OS y OP', 'Proformas de venta directa', 'Cuentas por cobrar'] as $texto) {
            $this->assertStringNotContainsString($texto, $vista);
        }
    }

    public function test_jefe_de_planta_no_se_ofrece_como_nuevo_rol(): void
    {
        $controlador = file_get_contents(app_path('Http/Controllers/UsuarioController.php'));

        $this->assertStringContainsString("where('codigo', '!=', 'JEFE_PLANTA')", $controlador);
        $this->assertStringContainsString('El rol Jefe de planta no forma parte de la versión reducida.', $controlador);
    }
}
