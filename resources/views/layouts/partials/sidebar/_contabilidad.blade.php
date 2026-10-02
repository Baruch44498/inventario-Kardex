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
                    <a href="{{ route('cuentas-cobrar.index') }}"
                        class="sidebar-link {{ request()->routeIs('cuentas-cobrar.*') ? 'sidebar-link--active' : '' }}" @if (request()->routeIs('cuentas-cobrar.*')) aria-current="page" @endif>
                        <span class="sidebar-link__icon"><x-ui.icon name="invoice" :size="16" /></span>
                        <span>Cuentas por cobrar</span>
                    </a>
                    <a href="{{ route('cuentas-pagar.index') }}"
                        class="sidebar-link {{ request()->routeIs('cuentas-pagar.*') ? 'sidebar-link--active' : '' }}" @if (request()->routeIs('cuentas-pagar.*')) aria-current="page" @endif>
                        <span class="sidebar-link__icon"><x-ui.icon name="coins" :size="16" /></span>
                        <span>Cuentas por pagar</span>
                    </a>
                    <a href="{{ route('tesoreria.movimientos.index') }}"
                        class="sidebar-link {{ request()->routeIs('tesoreria.*') ? 'sidebar-link--active' : '' }}" @if (request()->routeIs('tesoreria.*')) aria-current="page" @endif>
                        <span class="sidebar-link__icon"><x-ui.icon name="activity" :size="16" /></span>
                        <span>Movimientos de tesorería</span>
                    </a>
                </div>
            </details>
        @endif
