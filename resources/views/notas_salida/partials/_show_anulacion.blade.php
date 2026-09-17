<section class="panel entry-cancellation-panel">
    <div class="panel-heading"><p class="eyebrow">Anulación</p><h2>Estado de reversa</h2><p>La anulación restituye el stock y conserva los movimientos de reversa.</p></div>
    @if ($nota->estaAnulada())
        <div class="entry-cancellation-state entry-cancellation-state--danger"><span><x-ui.icon name="warning" :size="24" /></span><div><strong>Nota anulada</strong><p>{{ $nota->motivo_anulacion }}</p><small>Por {{ $nota->anulador?->username ?? 'usuario no disponible' }} · {{ $nota->anulado_en?->format('d/m/Y H:i') ?? 'fecha no disponible' }}</small></div></div>
    @elseif ($nota->estaConfirmada())
        <div class="entry-cancellation-state"><span><x-ui.icon name="info" :size="24" /></span><div><strong>La salida puede anularse</strong><p>El sistema devolverá las cantidades al inventario y registrará movimientos de reversa.</p><button type="button" class="button button--danger" data-open-output-cancel><x-ui.icon name="error" :size="17" /> Anular nota</button></div></div>
    @else
        <div class="entry-cancellation-state"><span><x-ui.icon name="check-circle" :size="24" /></span><div><strong>Sin acciones disponibles</strong><p>La nota conserva su estado y trazabilidad.</p></div></div>
    @endif
</section>
