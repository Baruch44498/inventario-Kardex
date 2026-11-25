        @if ($usuario->puede('produccion.ver'))
            <details class="sidebar-group" data-sidebar-group="produccion"
                data-active="{{ $produccionActivo ? 'true' : 'false' }}"
                @if ($produccionActivo || $esPlanta) open @endif>
                <summary class="sidebar-group__summary">
                    <span>Control de planta</span>
                    <span class="sidebar-group__chevron"><x-ui.icon name="chevron-down" :size="15" /></span>
                </summary>

                <div class="sidebar-group__content">
                    <a href="{{ route('ordenes-operacion.index', ['estado' => 'ACTIVAS']) }}"
                        class="sidebar-link {{ request()->routeIs('ordenes-operacion.*') ? 'sidebar-link--active' : '' }}">
                        <span class="sidebar-link__icon"><x-ui.icon name="activity" :size="16" /></span>
                        <span>Órdenes activas y avance</span>
                    </a>
                </div>
            </details>
        @endif
