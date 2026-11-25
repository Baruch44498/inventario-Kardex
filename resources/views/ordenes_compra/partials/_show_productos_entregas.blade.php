@if ($orden->situacionEntrega() === 'ATRASADA')
    <div class="notice notice--danger notice--block" role="alert">
        <x-ui.icon name="warning" :size="20" />
        <div>
            <strong>Entrega atrasada</strong>
            <p>{{ $orden->detallePlazoEntrega() }}. Aún existen productos pendientes de recepción; coordina con el proveedor o registra el ingreso cuando llegue la mercadería.</p>
        </div>
        @if ($puedeRegistrarIngreso)
            <a href="{{ route('notas-ingreso.create', ['motivo_ingreso' => 'COMPRA', 'orden_compra_id' => $orden->id]) }}" class="button button--ghost button--small">Registrar recepción</a>
        @endif
    </div>
@elseif ($orden->situacionEntrega() === 'VENCE_HOY')
    <div class="notice notice--warning notice--block" role="status">
        <x-ui.icon name="calendar" :size="20" />
        <div><strong>La entrega vence hoy</strong><p>La orden todavía tiene cantidades por recibir.</p></div>
    </div>
@elseif ($orden->situacionEntrega() === 'SIN_FECHA')
    <div class="notice notice--info notice--block" role="note">
        <x-ui.icon name="info" :size="20" />
        <div><strong>Entrega sin fecha acordada</strong><p>La orden permanece disponible para recepción, pero no puede clasificarse por vencimiento.</p></div>
    </div>
@elseif ($orden->permiteRecepcion())
    <div class="purchase-approval-gate" role="note">
        <span><x-ui.icon name="info" :size="19" /></span>
        <div>
            <strong>{{ $orden->estado === 'PARCIALMENTE_RECIBIDA' ? 'Recepción parcial: queda saldo pendiente' : 'Lista para la primera recepción' }}</strong>
            <p>La Nota de Ingreso mostrará únicamente las cantidades pendientes y actualizará automáticamente el estado de la orden.</p>
        </div>
    </div>
@elseif ($orden->estaRecibida())
    <div class="notice notice--success notice--block">
        <x-ui.icon name="check-circle" :size="20" />
        <div><strong>Recepción completada</strong><p>Todas las líneas de la orden fueron ingresadas al Almacén.</p></div>
    </div>
@endif

<section class="panel purchase-order-lines">
    <header class="supplier-panel-heading">
        <div><p class="eyebrow">Seguimiento de recepción</p><h2>Productos ordenados</h2></div>
        <span class="count-chip">{{ $orden->detalles->count() }}</span>
    </header>
    <div class="table-wrap table-wrap--cards" role="region" aria-label="Productos y avance de recepción" tabindex="0">
        <table class="data-table purchase-order-detail-table data-table--cards" role="table">
            <thead role="rowgroup">
                <tr role="row">
                    <th role="columnheader" scope="col">Producto</th>
                    <th role="columnheader" scope="col" class="text-right">Ordenado</th>
                    <th role="columnheader" scope="col" class="text-right">Recibido</th>
                    <th role="columnheader" scope="col" class="text-right">Pendiente</th>
                    <th role="columnheader" scope="col" class="text-right">Precio unitario</th>
                    <th role="columnheader" scope="col" class="text-right">Importe</th>
                    <th role="columnheader" scope="col">Avance</th>
                </tr>
            </thead>
            <tbody role="rowgroup">
                @foreach ($orden->detalles as $detalle)
                    @php
                        $pendiente = $detalle->cantidadPendiente();
                        $porcentaje = $detalle->porcentajeRecibido();
                    @endphp
                    <tr role="row">
                        <td role="cell" class="table-card__wide" data-label="Producto"><strong>{{ $detalle->producto?->codigo }}</strong><span>{{ $detalle->producto?->descripcion }}</span></td>
                        <td role="cell" class="text-right" data-label="Ordenado"><x-ui.quantity :value="$detalle->cantidad_ordenada" /></td>
                        <td role="cell" class="text-right" data-label="Recibido"><x-ui.quantity :value="$detalle->cantidad_recibida" /></td>
                        <td role="cell" class="text-right" data-label="Pendiente"><strong><x-ui.quantity :value="$pendiente" /></strong></td>
                        <td role="cell" class="text-right" data-label="Precio unitario"><x-ui.money :value="$detalle->precio_unitario" :currency="$orden->moneda" /></td>
                        <td role="cell" class="text-right" data-label="Importe"><strong><x-ui.money :value="$detalle->subtotal" :currency="$orden->moneda" /></strong></td>
                        <td role="cell" class="table-card__wide" data-label="Avance"><div class="purchase-order-progress"><span style="width: {{ number_format($porcentaje, 2, '.', '') }}%"></span></div><small>{{ number_format($porcentaje, 0) }}%</small></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
