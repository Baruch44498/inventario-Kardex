        @if ($usuario->puede('contabilidad.ver'))
            <details class="sidebar-group" data-sidebar-group="contabilidad"
                data-active="{{ $contabilidadActivo ? 'true' : 'false' }}"
                @if ($contabilidadActivo || $rol === 'CONTABILIDAD') open @endif>
                <summary class="sidebar-group__summary">
                    <span>Contabilidad</span>
                    <span class="sidebar-group__chevron"><x-ui.icon name="chevron-down" :size="15" /></span>
                </summary>
                <div class="sidebar-group__content">
                    <a href="{{ route('facturas-proveedor.index') }}"
                        class="sidebar-link {{ request()->routeIs('facturas-proveedor.*') ? 'sidebar-link--active' : '' }}">
                        <span class="sidebar-link__icon"><x-ui.icon name="invoice" :size="16" /></span>
                        <span>Facturas de proveedor</span>
                    </a>
                    <a href="{{ route('solicitudes-compra.index') }}"
                        class="sidebar-link {{ request()->routeIs('solicitudes-compra.*') ? 'sidebar-link--active' : '' }}">
                        <span class="sidebar-link__icon"><x-ui.icon name="clipboard" :size="16" /></span>
                        <span>Cotizaciones por pagar</span>
                    </a>
                    @if ($rol === 'CONTABILIDAD')
                        <a href="{{ route('ordenes-compra.index') }}"
                            class="sidebar-link {{ request()->routeIs('ordenes-compra.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="purchase-order" :size="16" /></span>
                            <span>Órdenes de compra</span>
                        </a>
                    @endif
                    <a href="{{ route('modulos.show', 'cuentas-cobrar') }}"
                        class="sidebar-link {{ request()->is('modulos/cuentas-cobrar') ? 'sidebar-link--active' : '' }}">
                        <span class="sidebar-link__icon"><x-ui.icon name="invoice" :size="16" /></span>
                        <span>Cuentas por cobrar</span>
                    </a>
                    <a href="{{ route('modulos.show', 'cuentas-pagar') }}"
                        class="sidebar-link {{ request()->is('modulos/cuentas-pagar') ? 'sidebar-link--active' : '' }}">
                        <span class="sidebar-link__icon"><x-ui.icon name="coins" :size="16" /></span>
                        <span>Cuentas por pagar</span>
                    </a>
                </div>
            </details>
        @endif
