<section class="panel product-detail-section">
    <header class="panel__header">
        <div>
            <p class="eyebrow">Conversión de compra</p>
            <h2>Presentaciones configuradas</h2>
            <p>El resultado siempre incrementa el stock en {{ $producto->unidad_codigo }}.</p>
        </div>
    </header>

    <div class="table-wrap table-wrap--cards">
        <table class="data-table product-detail-table data-table--cards" role="table">
            <thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Presentación</th><th role="columnheader" scope="col">Conversión a unidad base</th><th role="columnheader" scope="col">Estado</th></tr></thead>
            <tbody role="rowgroup">
                @forelse ($presentaciones as $presentacion)
                    <tr role="row">
                        <td role="cell" class="table-card__wide" data-label="Presentación">
                            <strong>{{ $presentacion->nombre }}</strong>
                            @if ($presentacion->es_predeterminada)
                                <span class="badge badge--info">PREDETERMINADA</span>
                            @endif
                        </td>
                        <td role="cell" data-label="Conversión a unidad base">1 {{ $presentacion->nombre }} = <x-ui.quantity :value="$presentacion->factor_conversion" /> {{ $producto->unidad_codigo }}</td>
                        <td role="cell" data-label="Estado"><span class="badge badge--{{ $presentacion->estado ? 'success' : 'neutral' }}">{{ $presentacion->estado ? 'ACTIVA' : 'INACTIVA' }}</span></td>
                    </tr>
                @empty
                    <tr role="row"><td role="cell" colspan="3">Sin presentaciones adicionales. El producto se compra directamente en su unidad base.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
