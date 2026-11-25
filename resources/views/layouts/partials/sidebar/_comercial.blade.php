        @if ($usuario->puedeAlguno(
            'clientes.gestionar',
            'proformas.cotizar',
            'ordenes.crear_comercial'
        ))
            <details class="sidebar-group" data-sidebar-group="comercial"
                data-active="{{ $comercialActivo ? 'true' : 'false' }}"
                @if ($comercialActivo || $esLogistica) open @endif>
                <summary class="sidebar-group__summary">
                    <span>Comercial y logística</span>
                    <span class="sidebar-group__chevron"><x-ui.icon name="chevron-down" :size="15" /></span>
                </summary>

                <div class="sidebar-group__content">
                    @if ($usuario->puede('clientes.gestionar'))
                        <a href="{{ route('clientes.index') }}"
                            class="sidebar-link {{ request()->routeIs('clientes.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="users" :size="16" /></span>
                            <span>Clientes</span>
                        </a>
                        <a href="{{ route('tipos-cliente.index') }}"
                            class="sidebar-link {{ request()->routeIs('tipos-cliente.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="tag" :size="16" /></span>
                            <span>Tipos de cliente</span>
                        </a>
                    @endif

                    @if ($usuario->puede('proformas.cotizar'))
                        <a href="{{ route('cotizaciones-cliente.index') }}"
                            class="sidebar-link {{ request()->routeIs('cotizaciones-cliente.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="quotes" :size="16" /></span>
                            <span>Cotizaciones al cliente</span>
                        </a>
                        <a href="{{ route('plantillas-costeo.index') }}"
                            class="sidebar-link {{ request()->routeIs('plantillas-costeo.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="clipboard" :size="16" /></span>
                            <span>Plantillas de costeo</span>
                        </a>

                        @if (! $esAdministrador)
                            <a href="{{ route('proformas.index') }}"
                                class="sidebar-link {{ request()->routeIs('proformas.*') ? 'sidebar-link--active' : '' }}">
                                <span class="sidebar-link__icon"><x-ui.icon name="clipboard" :size="16" /></span>
                                <span>Proformas recibidas</span>
                            </a>
                        @endif
                    @endif

                    @if ($usuario->puede('ordenes.crear_comercial') || $esAdministrador)
                        <a href="{{ route('ordenes-operacion.index') }}"
                            class="sidebar-link {{ request()->routeIs('ordenes-operacion.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="orders" :size="16" /></span>
                            <span>Órdenes OM, OS y OP</span>
                        </a>
                    @endif
                </div>
            </details>
        @endif
