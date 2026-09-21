        @if (auth()->user()->puede('compras.gestionar'))
            <section class="role-quick-grid">
                <a href="{{ route('cotizaciones-proveedor.index') }}" class="role-quick-card"><span><x-ui.icon name="quotes" :size="22" /></span><div><strong>Cotizaciones de proveedores</strong><small>Registrar, clasificar y aprobar compras.</small></div></a>
                <a href="{{ route('solicitudes-compra.index') }}" class="role-quick-card"><span><x-ui.icon name="clipboard" :size="22" /></span><div><strong>Compras aprobadas</strong><small>Consultar decisiones y sus órdenes.</small></div></a>
                <a href="{{ route('ordenes-compra.index') }}" class="role-quick-card"><span><x-ui.icon name="purchase-order" :size="22" /></span><div><strong>Órdenes de compra</strong><small>Emitir y seguir recepciones.</small></div></a>
            </section>
        @endif

        <section class="metric-grid metric-grid--four" aria-label="Indicadores de órdenes">
            @foreach ([
                ['Órdenes abiertas', 'orders', 'info', $resumen['abiertas'], 'Pendientes de iniciar'],
                ['En proceso', 'activity', 'warning', $resumen['en_proceso'], 'Ejecución activa'],
                ['Cerradas este mes', 'check-circle', 'success', $resumen['cerradas_mes'], 'Trabajo finalizado'],
                ['Salidas hoy', 'exit', 'info', $resumen['salidas_hoy'], 'Materiales entregados'],
            ] as [$titulo, $icono, $tono, $valor, $detalle])
                <article class="metric-card metric-card--{{ $tono }}">
                    <div class="metric-card__top">
                        <span class="metric-card__label">{{ $titulo }}</span>
                        <span class="metric-card__icon">
                            <x-ui.icon :name="$icono" :size="22" />
                        </span>
                    </div>
                    <strong>{{ number_format($valor) }}</strong>
                    <small>{{ $detalle }}</small>
                </article>
            @endforeach
        </section>

        @include('dashboard._bandeja_operativa')

        <section class="panel role-order-panel {{ $ordenesRecientes->isEmpty() ? 'panel--empty-list' : '' }}">
            <header class="panel__header">
                <div>
                    <p class="eyebrow">Operación</p>
                    <h2>Órdenes activas</h2>
                </div>
                <a href="{{ route('ordenes-operacion.index', ['estado' => 'ACTIVAS']) }}" class="text-link">
                    Ver órdenes
                </a>
            </header>

            @if ($ordenesRecientes->isNotEmpty())
                <div class="table-wrap role-order-table-wrap">
                    <table class="data-table role-order-table">
                        <thead>
                            <tr>
                                <th>Orden</th>
                                <th>Cliente / vehículo</th>
                                <th>Apertura</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ordenesRecientes as $orden)
                                @php
                                    $tonoOrden = match ($orden->estado) {
                                        'ABIERTA' => 'info',
                                        'EN_PROCESO' => 'warning',
                                        'CERRADA' => 'success',
                                        'ANULADA' => 'danger',
                                        default => 'neutral',
                                    };
                                @endphp
                                <tr>
                                    <td class="role-order-table__code">
                                        <a href="{{ route('ordenes-operacion.show', $orden->id) }}" class="table-primary-link">
                                            {{ $orden->codigo_orden }}
                                        </a>
                                        <span class="role-order-table__mobile-date">
                                            {{ $orden->fecha_apertura?->format('d/m/Y') }}
                                        </span>
                                    </td>
                                    <td class="role-order-table__client">
                                        <strong>{{ $orden->cliente?->nombreVisible() ?? 'Sin cliente' }}</strong>
                                        @if ($orden->vehiculo)
                                            <span>{{ $orden->vehiculo->identificadorVisible() }}</span>
                                        @endif
                                    </td>
                                    <td class="role-order-table__date">{{ $orden->fecha_apertura?->format('d/m/Y') }}</td>
                                    <td>
                                        <x-ui.status-badge :tone="$tonoOrden">
                                            {{ str_replace('_', ' ', $orden->estado) }}
                                        </x-ui.status-badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-ui.empty-table
                    icon="orders"
                    title="Sin órdenes activas"
                    description="No existen órdenes abiertas o en proceso."
                />
            @endif
        </section>
