<section class="panel product-detail-section">
    <header class="panel__header">
        <div><p class="eyebrow">Ubicaciones</p><h2>Inventario por repisa</h2></div>
        <a href="{{ route('inventario.index', ['q' => $producto->codigo]) }}" class="text-link">Ver en inventario</a>
    </header>

    <div class="table-wrap">
        <table class="data-table product-detail-table">
            <thead>
                <tr><th>Repisa</th><th class="text-right">Stock</th><th class="text-right">Mínimo</th><th class="text-right">Objetivo</th><th class="text-right">Costo prom.</th><th>Estado</th></tr>
            </thead>
            <tbody>
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
                    <tr>
                        <td><strong>{{ $item->repisa_codigo }}</strong><span>{{ $item->repisa_descripcion ?? 'Sin descripción' }}</span></td>
                        <td class="text-right"><x-ui.quantity :value="$item->stock_actual" /></td>
                        <td class="text-right"><x-ui.quantity :value="$item->stock_minimo" /></td>
                        <td class="text-right">@if ($item->stock_maximo === null) — @else <x-ui.quantity :value="$item->stock_maximo" /> @endif</td>
                        <td class="text-right">S/ {{ number_format((float) $item->costo_promedio_soles, 2, '.', ',') }}</td>
                        <td><span class="badge badge--{{ $badge }}">{{ $label }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6">Producto sin inventario asignado. Aparecerá aquí cuando tenga existencias en una repisa.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
