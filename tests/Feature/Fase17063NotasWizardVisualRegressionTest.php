<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase17063NotasWizardVisualRegressionTest extends TestCase
{
    public function test_notas_de_salida_conservan_el_wizard_hasta_la_confirmacion(): void
    {
        $create = file_get_contents(resource_path('views/notas_salida/create.blade.php'));
        $show = file_get_contents(resource_path('views/notas_salida/show.blade.php'));

        $this->assertStringContainsString('data-document-note-wizard', $create);
        $this->assertStringContainsString('data-note-wizard-next', $create);
        $this->assertStringContainsString("asset('js/document-note-wizard.js')", $create);
        $this->assertStringContainsString('document-flow-page--completed', $show);
        $this->assertStringContainsString(':current="5"', $show);
        $this->assertStringContainsString('entry-document-grid output-show-grid', $show);
        $this->assertStringContainsString('detail-list detail-list--entry', $show);
        $this->assertStringNotContainsString('entry-show-grid output-show-grid', $show);
        $this->assertStringNotContainsString('detail-list detail-list--two-columns', $show);
        $this->assertStringNotContainsString('{{ $cliente }}', $show);
        $this->assertStringNotContainsString('{{ $vehiculo }}', $show);
    }

    public function test_notas_de_ingreso_usan_el_mismo_patron_visual(): void
    {
        $create = file_get_contents(resource_path('views/notas_ingreso/create.blade.php'));
        $show = file_get_contents(resource_path('views/notas_ingreso/show.blade.php'));

        $this->assertStringContainsString('data-document-note-wizard', $create);
        $this->assertStringContainsString('data-note-wizard-next', $create);
        $this->assertStringContainsString("asset('js/document-note-wizard.js')", $create);
        $this->assertStringContainsString('document-flow-page--completed', $show);
        $this->assertStringContainsString(':current="5"', $show);
        $this->assertStringContainsString('entry-document-grid', $show);
        $this->assertStringContainsString('detail-list detail-list--entry', $show);
        $this->assertStringContainsString('table-wrap--responsive', $show);
    }

    public function test_stepper_puede_renderizarse_como_resumen_no_interactivo(): void
    {
        $component = file_get_contents(resource_path('views/components/ui/workflow-stepper.blade.php'));

        $this->assertStringContainsString("'interactive' => true", $component);
        $this->assertStringContainsString('@disabled(! $interactive || $number > $current)', $component);
    }

    public function test_javascript_del_wizard_controla_los_cuatro_pasos(): void
    {
        $script = file_get_contents(public_path('js/document-note-wizard.js'));

        $this->assertStringContainsString("[data-document-note-wizard]", $script);
        $this->assertStringContainsString('showStep(currentStep + 1)', $script);
        $this->assertStringContainsString('Ingresa al menos una cantidad mayor que 0.', $script);
        $this->assertStringContainsString("currentStep !== 4", $script);
    }
}
