<section class="panel product-detail-section">
    <header class="panel__header">
        <div><p class="eyebrow">Trazabilidad</p><h2>Últimos movimientos</h2><p>Entradas, salidas y ajustes recientes del producto.</p></div>
        @if ($puedeVerMovimientos)
            <a href="{{ route('movimientos.index', ['q' => $producto->codigo]) }}" class="text-link">Ver todos los movimientos</a>
        @endif
    </header>

    @if (! $puedeVerMovimientos)
        <div class="product-detail-restricted">
            <x-ui.icon name="lock" :size="24" />
            <div><strong>Información restringida</strong><span>La trazabilidad está disponible para usuarios con permiso de Movimientos.</span></div>
        </div>
    @elseif ($movimientos->isNotEmpty())
        <div class="table-wrap table-wrap--cards">
            <table class="data-table product-detail-table data-table--cards" role="table">
                <thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Fecha</th><th role="columnheader" scope="col">Repisa</th><th role="columnheader" scope="col">Tipo</th><th role="columnheader" scope="col" class="text-right">Cantidad</th><th role="columnheader" scope="col">Flujo de stock</th><th role="columnheader" scope="col">Acción</th></tr></thead>
                <tbody role="rowgroup">
                    @foreach ($movimientos as $movimiento)
                        @php
                            $esEntrada = $movimiento->tipo_movimiento === 'ENTRADA';
                            $esAjusteCosto = $movimiento->tipo_movimiento === 'AJUSTE_COSTO';
                        @endphp
                        <tr role="row">
                            <td role="cell" data-label="Fecha"><strong>{{ \Illuminate\Support\Carbon::parse($movimiento->fecha_movimiento)->format('d/m/Y') }}</strong><span>{{ \Illuminate\Support\Carbon::parse($movimiento->fecha_movimiento)->format('H:i') }}</span></td>
                            <td role="cell" data-label="Repisa"><span class="location-chip"><x-ui.icon name="shelf" :size="14" />{{ $movimiento->repisa_codigo }}</span></td>
                            <td role="cell" data-label="Tipo"><span class="badge badge--{{ $esAjusteCosto ? 'info' : ($esEntrada ? 'success' : 'danger') }}">{{ $esAjusteCosto ? 'AJUSTE DE COSTO' : ($esEntrada ? 'ENTRADA' : 'SALIDA') }}</span><span>{{ str($movimiento->motivo)->replace('_', ' ')->title() }}</span></td>
                            <td role="cell" class="text-right" data-label="Cantidad">{{ $esAjusteCosto ? '—' : ($esEntrada ? '+' : '−') }}@if (! $esAjusteCosto)<x-ui.quantity :value="$movimiento->cantidad" />@endif</td>
                            <td role="cell" class="table-card__wide" data-label="Flujo de stock"><span class="stock-flow"><x-ui.quantity :value="$movimiento->stock_anterior" /><x-ui.icon name="arrow-right" :size="14" /><strong><x-ui.quantity :value="$movimiento->stock_posterior" /></strong></span></td>
                            <td role="cell" class="table-card__wide" data-label="Acción"><a href="{{ route('movimientos.show', $movimiento->id) }}" class="icon-button" title="Ver movimiento" aria-label="Ver movimiento"><x-ui.icon name="eye" :size="17" /></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="product-detail-empty"><strong>Sin movimientos registrados</strong><span>Las operaciones del producto aparecerán aquí.</span></div>
    @endif
</section>
