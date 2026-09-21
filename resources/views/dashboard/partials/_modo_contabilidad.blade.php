        <section class="metric-grid metric-grid--four" aria-label="Indicadores contables">
            @foreach ([
                ['Cotizaciones por pagar', 'clipboard', 'warning', $resumen['compras_por_pagar'], 'Aprobadas por Compras'],
                ['Órdenes cerradas', 'check-circle', 'success', $resumen['ordenes_cerradas'], 'Disponibles para el puente'],
                ['Salidas confirmadas', 'exit', 'info', $resumen['salidas_confirmadas'], 'Despachos registrados'],
                ['Cerradas hoy', 'clipboard', 'warning', $resumen['documentos_hoy'], 'Documentos del día'],
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

        <section class="role-quick-grid">
            <a href="{{ route('solicitudes-compra.index', ['estado' => 'CONVERTIDA']) }}" class="role-quick-card">
                <span><x-ui.icon name="clipboard" :size="24" /></span>
                <div>
                    <strong>Cotizaciones por pagar</strong>
                    <small>Consultar compras aprobadas y copiar sus datos de pago.</small>
                </div>
            </a>
            <a href="{{ route('ordenes-compra.index') }}" class="role-quick-card">
                <span><x-ui.icon name="purchase-order" :size="24" /></span>
                <div><strong>Órdenes de compra</strong><small>Consultar las compras emitidas por Logística / Compras.</small></div>
            </a>
            <a href="{{ route('modulos.show', 'cuentas-cobrar') }}" class="role-quick-card">
                <span><x-ui.icon name="invoice" :size="24" /></span>
                <div>
                    <strong>Cuentas por cobrar</strong>
                    <small>Órdenes y ventas finalizadas.</small>
                </div>
            </a>
            <a href="{{ route('modulos.show', 'cuentas-pagar') }}" class="role-quick-card">
                <span><x-ui.icon name="coins" :size="24" /></span>
                <div>
                    <strong>Cuentas por pagar</strong>
                    <small>Facturas de proveedores aprobadas.</small>
                </div>
            </a>
        </section>
