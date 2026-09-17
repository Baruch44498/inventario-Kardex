<section class="panel entry-cancellation-panel">
    <div class="panel-heading">
        <p class="eyebrow">Anulación</p>
        <h2>Estado de reversa</h2>
        <p>La anulación revierte el efecto físico y conserva la evidencia del ingreso original.</p>
    </div>

    @if ($nota->estaAnulada())
        <div class="entry-cancellation-state entry-cancellation-state--danger">
            <span><x-ui.icon name="warning" :size="24" /></span>
            <div>
                <strong>Recepción anulada</strong>
                <p>{{ $nota->motivo_anulacion }}</p>
                <small>Por {{ $nota->anulador?->username ?? 'usuario no disponible' }} · {{ $nota->anulado_en?->format('d/m/Y H:i') ?? 'fecha no disponible' }}</small>
            </div>
        </div>
    @elseif ($puedeAnular)
        <div class="entry-cancellation-state">
            <span><x-ui.icon name="info" :size="24" /></span>
            <div>
                <strong>La recepción puede anularse</strong>
                <p>El sistema retirará las cantidades del inventario, registrará movimientos de reversa y devolverá el saldo a la OC y al requerimiento.</p>
                <button type="button" class="button button--danger" data-open-entry-cancel><x-ui.icon name="error" :size="17" /> Anular recepción</button>
            </div>
        </div>
    @else
        <div class="entry-cancellation-state">
            <span><x-ui.icon name="check-circle" :size="24" /></span>
            <div><strong>Sin acciones de anulación disponibles</strong><p>La nota conserva su estado actual y toda su trazabilidad.</p></div>
        </div>
    @endif
</section>
