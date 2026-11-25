        @if ($usuario->puedeAlguno('proveedores.gestionar', 'compras.gestionar', 'requerimientos.compra.gestionar'))
            <details class="sidebar-group" data-sidebar-group="compras"
                data-active="{{ $comprasActivo ? 'true' : 'false' }}"
                @if ($comprasActivo || $esLogistica) open @endif>
                <summary class="sidebar-group__summary">
                    <span>Compras y proveedores</span>
                    <span class="sidebar-group__chevron"><x-ui.icon name="chevron-down" :size="15" /></span>
                </summary>

                <div class="sidebar-group__content">
                    @if ($usuario->puede('proveedores.gestionar'))
                        <a href="{{ route('proveedores.index') }}"
                            class="sidebar-link {{ request()->routeIs('proveedores.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="suppliers" :size="16" /></span>
                            <span>Proveedores</span>
                        </a>
                    @endif

                    @if ($usuario->puede('requerimientos.compra.gestionar'))
                        <a href="{{ route('requerimientos-compra.index') }}"
                            class="sidebar-link {{ request()->routeIs('requerimientos-compra.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="requisitions" :size="16" /></span>
                            <span>Requerimientos de compra</span>
                        </a>
                    @endif

                    @if ($usuario->puede('compras.gestionar'))
                        <a href="{{ route('cotizaciones-proveedor.index') }}"
                            class="sidebar-link {{ request()->routeIs('cotizaciones-proveedor.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="quotes" :size="16" /></span>
                            <span>Cotizaciones de proveedores</span>
                        </a>
                        <a href="{{ route('historial-precios.index') }}"
                            class="sidebar-link {{ request()->routeIs('historial-precios.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="banknote" :size="16" /></span>
                            <span>Historial de precios</span>
                        </a>
                        <a href="{{ route('solicitudes-compra.index') }}"
                            class="sidebar-link {{ request()->routeIs('solicitudes-compra.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="clipboard" :size="16" /></span>
                            <span>Compras aprobadas</span>
                        </a>

                        <a href="{{ route('ordenes-compra.index') }}"
                            class="sidebar-link {{ request()->routeIs('ordenes-compra.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="purchase-order" :size="16" /></span>
                            <span>Órdenes de compra</span>
                        </a>

                        <a href="{{ route('facturas-proveedor.index') }}"
                            class="sidebar-link {{ request()->routeIs('facturas-proveedor.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="invoice" :size="16" /></span>
                            <span>Facturas de proveedor</span>
                        </a>
                    @endif
                </div>
            </details>
        @endif
