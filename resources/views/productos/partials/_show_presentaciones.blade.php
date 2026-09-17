<section class="panel product-detail-section">
    <header class="panel__header">
        <div>
            <p class="eyebrow">Conversión de compra</p>
            <h2>Presentaciones configuradas</h2>
            <p>El resultado siempre incrementa el stock en {{ $producto->unidad_codigo }}.</p>
        </div>
    </header>

    <div class="table-wrap">
        <table class="data-table product-detail-table">
            <thead><tr><th>Presentación</th><th>Conversión a unidad base</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse ($presentaciones as $presentacion)
                    <tr>
                        <td>
                            <strong>{{ $presentacion->nombre }}</strong>
                            @if ($presentacion->es_predeterminada)
                                <span class="badge badge--info">PREDETERMINADA</span>
                            @endif
                        </td>
                        <td>1 {{ $presentacion->nombre }} = <x-ui.quantity :value="$presentacion->factor_conversion" /> {{ $producto->unidad_codigo }}</td>
                        <td><span class="badge badge--{{ $presentacion->estado ? 'success' : 'neutral' }}">{{ $presentacion->estado ? 'ACTIVA' : 'INACTIVA' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3">Sin presentaciones adicionales. El producto se compra directamente en su unidad base.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
