@php
    $stockTotal = $inventarios->sum('stock_actual');
    $valorTotal = $inventarios->sum(
        fn ($item) => (float) $item->stock_actual * (float) $item->costo_promedio_soles
    );
    $ubicacionesConStock = $inventarios->filter(
        fn ($item) => (float) $item->stock_actual > 0
    )->count();
@endphp

<section class="product-detail-summary-grid">
    <article class="panel detail-card">
        <header class="detail-card__header">
            <span class="detail-card__icon"><x-ui.icon name="products" :size="22" /></span>
            <div><p class="eyebrow">Información general</p><h2>Datos maestros</h2></div>
        </header>

        <dl class="description-list">
            <div><dt>Código</dt><dd>{{ $producto->codigo }}</dd></div>
            <div><dt>Unidad base</dt><dd>{{ $producto->unidad_codigo }} · {{ $producto->unidad_nombre }}</dd></div>
            <div>
                <dt>Fraccionamiento</dt>
                <dd>
                    <span class="badge badge--{{ $producto->permite_fraccionamiento ? 'success' : 'neutral' }}">
                        {{ $producto->permite_fraccionamiento ? 'PERMITE DECIMALES' : 'SOLO ENTEROS' }}
                    </span>
                </dd>
            </div>
            <div><dt>Marca principal</dt><dd>{{ $producto->marca_nombre ?? 'Sin marca asignada' }}</dd></div>
            <div>
                <dt>Última actualización</dt>
                <dd>{{ $producto->actualizado_en ? \Carbon\Carbon::parse($producto->actualizado_en)->format('d/m/Y H:i') : '—' }}</dd>
            </div>
        </dl>
    </article>

    <article class="panel detail-card">
        <header class="detail-card__header">
            <span class="detail-card__icon"><x-ui.icon name="inventory" :size="22" /></span>
            <div><p class="eyebrow">Existencias</p><h2>Resumen actual</h2></div>
        </header>

        <div class="mini-metric-grid">
            <div class="mini-metric"><span>Stock total</span><strong><x-ui.quantity :value="$stockTotal" /> {{ $producto->unidad_codigo }}</strong></div>
            <div class="mini-metric"><span>Ubicaciones con stock</span><strong>{{ $ubicacionesConStock }}</strong></div>
            <div class="mini-metric"><span>Valor estimado</span><strong>S/ {{ number_format($valorTotal, 2, '.', ',') }}</strong></div>
        </div>
    </article>
</section>
