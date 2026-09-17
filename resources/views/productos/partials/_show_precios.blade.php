<section class="panel product-detail-section">
    <header class="panel__header">
        <div><p class="eyebrow">Compras</p><h2>Precios recientes de proveedores</h2><p>Referencia histórica; no modifica el costo actual del inventario.</p></div>
        @if ($puedeVerPrecios)
            <a href="{{ route('historial-precios.index', ['producto_id' => $producto->id_producto]) }}" class="text-link">Ver historial completo</a>
        @endif
    </header>

    @if (! $puedeVerPrecios)
        <div class="product-detail-restricted">
            <x-ui.icon name="lock" :size="24" />
            <div><strong>Información restringida</strong><span>El historial de precios está disponible para usuarios con permiso de Compras.</span></div>
        </div>
    @elseif ($precios->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table product-detail-table">
                <thead><tr><th>Fecha</th><th>Proveedor</th><th>Moneda</th><th class="text-right">Precio final</th><th class="text-right">Equiv. PEN</th><th>Cotización</th></tr></thead>
                <tbody>
                    @foreach ($precios as $precio)
                        @php($equivalente = $precio->precioEquivalentePen())
                        <tr>
                            <td>{{ $precio->cotizacion->fecha_cotizacion->format('d/m/Y') }}</td>
                            <td>{{ $precio->cotizacion->proveedor->nombreVisible() }}</td>
                            <td><span class="currency-chip">{{ $precio->cotizacion->moneda }}</span></td>
                            <td class="text-right"><strong>{{ $precio->cotizacion->simboloMoneda() }} {{ number_format($precio->precioFinalUnitario(), 2) }}</strong></td>
                            <td class="text-right">{{ $equivalente !== null ? 'S/ '.number_format($equivalente, 2) : '—' }}</td>
                            <td><a href="{{ route('cotizaciones-proveedor.show', $precio->cotizacion_id) }}" class="table-primary-link">{{ $precio->cotizacion->codigo }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="product-detail-empty"><strong>Sin precios registrados</strong><span>Las ofertas aparecerán al registrar cotizaciones de proveedor.</span></div>
    @endif
</section>
