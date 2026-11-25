<section id="paso-confirmacion" class="panel entry-confirmation-panel" data-flow-step-section="4">
    <span class="entry-confirmation-panel__icon"><x-ui.icon name="check-circle" :size="26" /></span>
    <div class="entry-confirmation-panel__copy">
        <p class="eyebrow">Confirmación</p>
        <h2>Revisa antes de incrementar el stock</h2>
        <p>
            @if ($motivo === 'COMPRA')
                Al confirmar se incrementará el stock y la OC quedará en “Recepción parcial” mientras conserve saldos, o “Recibida completamente” al completar todas sus líneas.
            @elseif ($motivo === 'DEVOLUCION_MATERIAL_MALOGRADO')
                Se registrará la pérdida contra la orden y el área, pero el material no incrementará el stock utilizable.
            @else
                Al confirmar se registra una entrada real al Kardex. Una devolución de herramienta deja de estar pendiente; una reposición reduce el saldo del préstamo.
            @endif
        </p>
    </div>
</section>
