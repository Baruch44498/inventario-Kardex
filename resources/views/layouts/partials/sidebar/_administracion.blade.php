        @if ($usuario->puedeAlguno('usuarios.gestionar', 'empleados.gestionar', 'kardex.ver', 'auditoria.ver'))
            <details class="sidebar-group" data-sidebar-group="administracion"
                data-active="{{ $administracionActiva ? 'true' : 'false' }}"
                @if ($administracionActiva) open @endif>
                <summary class="sidebar-group__summary">
                    <span>Administración del sistema</span>
                    <span class="sidebar-group__chevron"><x-ui.icon name="chevron-down" :size="15" /></span>
                </summary>
                <div class="sidebar-group__content">
                    @if ($usuario->puede('usuarios.gestionar'))
                        <a href="{{ route('usuarios.index') }}"
                            class="sidebar-link {{ request()->routeIs('usuarios.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="users" :size="16" /></span>
                            <span>Usuarios y permisos</span>
                        </a>
                    @endif
                    @if ($usuario->puede('empleados.gestionar'))
                        <a href="{{ route('empleados.index') }}"
                            class="sidebar-link {{ request()->routeIs('empleados.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="id-card" :size="16" /></span>
                            <span>Empleados</span>
                        </a>
                    @endif
                    @if ($usuario->puede('kardex.ver'))
                        <a href="{{ route('kardex.index') }}"
                            class="sidebar-link {{ request()->routeIs('kardex.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="coins" :size="16" /></span>
                            <span>Kardex valorizado</span>
                        </a>
                    @endif
                    @if ($usuario->puede('auditoria.ver'))
                        <a href="{{ route('modulos.show', 'auditoria') }}"
                            class="sidebar-link {{ request()->is('modulos/auditoria') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="clipboard" :size="16" /></span>
                            <span>Auditoría</span>
                        </a>
                    @endif
                </div>
            </details>
        @endif
