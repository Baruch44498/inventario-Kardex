<section class="panel entry-trace-panel">
    <div class="panel-heading panel-heading--split">
        <div><p class="eyebrow">Trazabilidad</p><h2>Registro y seguimiento</h2><p>Relación entre el origen, el usuario y los movimientos generados.</p></div>
        <a href="{{ route('movimientos.index', ['q' => $nota->id]) }}" class="button button--ghost button--small"><x-ui.icon name="movements" :size="16" /> Ver movimientos</a>
    </div>

    <dl class="entry-trace-grid">
        <div><dt>Origen</dt><dd>{{ $origen }}</dd></div>
        <div><dt>Registrado por</dt><dd>{{ $nota->registrador?->username ?? '—' }}</dd></div>
        <div><dt>Confirmado</dt><dd>{{ $nota->confirmado_en?->format('d/m/Y H:i') ?? '—' }}</dd></div>
        <div><dt>Estado</dt><dd><span class="badge badge--{{ $estadoClase }}">{{ $nota->estado }}</span></dd></div>
    </dl>

    @if ($nota->ordenCompra)
        <div class="notice notice--{{ $nota->ordenCompra->estaRecibida() ? 'success' : 'warning' }} notice--block">
            <x-ui.icon :name="$nota->ordenCompra->estaRecibida() ? 'check-circle' : 'inventory'" :size="20" />
            <div>
                <strong>{{ $nota->ordenCompra->estadoVisible() }}</strong>
                <p>{{ $nota->ordenCompra->estaRecibida() ? 'Esta nota completó la recepción de la orden.' : 'La orden conserva cantidades pendientes. Registra otra recepción cuando llegue el saldo.' }}</p>
            </div>
            <a href="{{ route('ordenes-compra.show', $nota->ordenCompra) }}" class="button button--ghost button--small">Ver orden y saldo</a>
        </div>
    @endif

    @if ($nota->observacion)
        <div class="notice notice--info notice--block"><x-ui.icon name="info" :size="18" /><span>{{ $nota->observacion }}</span></div>
    @endif
</section>
