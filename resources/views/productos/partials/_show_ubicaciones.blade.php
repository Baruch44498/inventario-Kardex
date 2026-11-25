<section class="panel product-detail-section">
    <header class="panel__header">
        <div><p class="eyebrow">Ubicaciones</p><h2>Inventario por repisa</h2></div>
        <a href="{{ route('inventario.index', ['q' => $producto->codigo]) }}" class="text-link">Ver en inventario</a>
    </header>

    <div class="table-wrap table-wrap--cards">
        <table class="data-table product-detail-table data-table--cards" role="table">
            <thead role="rowgroup">
                <tr role="row"><th role="columnheader" scope="col">Repisa</th><th role="columnheader" scope="col" class="text-right">Stock</th><th role="columnheader" scope="col" class="text-right">Mínimo</th><th role="columnheader" scope="col" class="text-right">Objetivo</th><th role="columnheader" scope="col" class="text-right">Costo prom.</th><th role="columnheader" scope="col">Estado</th></tr>
            </thead>
            <tbody role="rowgroup">
                @forelse ($inventarios as $item)
                    @php
                        $badge = match ($item->estado_stock) {
                            'SIN_STOCK' => 'danger',
                            'BAJO_MINIMO' => 'warning',
                            'SOBRE_MAXIMO' => 'info',
                            default => 'success',
                        };
                        $label = match ($item->estado_stock) {
                            'SIN_STOCK' => 'SIN STOCK',
                            'BAJO_MINIMO' => 'BAJO MÍNIMO',
                            'SOBRE_MAXIMO' => 'SOBRE MÁXIMO',
                            default => 'NORMAL',
                        };
                    @endphp
                    <tr role="row">
                        <td role="cell" class="table-card__wide" data-label="Repisa"><strong>{{ $item->repisa_codigo }}</strong><span>{{ $item->repisa_descripcion ?? 'Sin descripción' }}</span></td>
                        <td role="cell" class="text-right" data-label="Stock"><x-ui.quantity :value="$item->stock_actual" /></td>
                        <td role="cell" class="text-right" data-label="Mínimo"><x-ui.quantity :value="$item->stock_minimo" /></td>
                        <td role="cell" class="text-right" data-label="Objetivo">@if ($item->stock_maximo === null) — @else <x-ui.quantity :value="$item->stock_maximo" /> @endif</td>
                        <td role="cell" class="text-right" data-label="Costo prom.">S/ {{ number_format((float) $item->costo_promedio_soles, 2, '.', ',') }}</td>
                        <td role="cell" data-label="Estado"><span class="badge badge--{{ $badge }}">{{ $label }}</span></td>
                    </tr>
                @empty
                    <tr role="row"><td role="cell" colspan="6">Producto sin inventario asignado. Aparecerá aquí cuando tenga existencias en una repisa.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
