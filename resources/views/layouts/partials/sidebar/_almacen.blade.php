        @if ($usuario->puedeAlguno(
            'productos.ver',
            'inventario.ver',
            'repisas.ver',
            'movimientos.ver',
            'ingresos.ver',
            'salidas.listar',
            'alertas.ver',
            'requerimientos.compra.crear',
            'proformas.crear'
        ))
            <details class="sidebar-group" data-sidebar-group="almacen"
                data-active="{{ $almacenActivo ? 'true' : 'false' }}"
                @if ($almacenActivo || $esAlmacen) open @endif>
                <summary class="sidebar-group__summary">
                    <span>Almacén</span>
                    <span class="sidebar-group__chevron"><x-ui.icon name="chevron-down" :size="15" /></span>
                </summary>

                <div class="sidebar-group__content">
                    @if ($usuario->puede('inventario.ver'))
                        {{-- Inventario accesible --}}
                    @endif
                    @foreach ([
                        ['productos.ver', 'productos.index', 'productos.*', 'products', 'Productos'],
                        ['inventario.ver', 'inventario.index', 'inventario.*', 'inventory', 'Inventario'],
                        ['inventario.ver', 'inventarios-periodicos.index', 'inventarios-periodicos.*', 'clipboard', 'Inventarios periódicos'],
                        ['repisas.ver', 'repisas.index', 'repisas.*', 'shelf', 'Repisas'],
                        ['movimientos.ver', 'movimientos.index', 'movimientos.*', 'movements', 'Movimientos'],
                        ['ingresos.ver', 'notas-ingreso.index', 'notas-ingreso.*', 'entry', 'Notas de ingreso'],
                        ['salidas.listar', 'notas-salida.index', 'notas-salida.*', 'exit', 'Notas de salida'],
                        ['alertas.ver', 'alertas.index', 'alertas.*', 'alerts', 'Alertas de stock'],
                    ] as [$permiso, $ruta, $patron, $icono, $nombre])
                        @if ($usuario->puede($permiso))
                            <a href="{{ route($ruta) }}"
                                class="sidebar-link {{ request()->routeIs($patron) ? 'sidebar-link--active' : '' }}">
                                <span class="sidebar-link__icon"><x-ui.icon :name="$icono" :size="16" /></span>
                                <span>{{ $nombre }}</span>
                            </a>
                        @endif
                    @endforeach

                    @if ($esAlmacen && $usuario->puede('ingresos.ver'))
                        <a href="{{ route('facturas-proveedor.index') }}"
                            class="sidebar-link {{ request()->routeIs('facturas-proveedor.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="invoice" :size="16" /></span>
                            <span>Facturas de proveedor</span>
                        </a>
                        <a href="{{ route('ordenes-compra.index') }}"
                            class="sidebar-link {{ request()->routeIs('ordenes-compra.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="purchase-order" :size="16" /></span>
                            <span>Órdenes por recibir</span>
                        </a>
                    @endif

                    @if ($esAlmacen && $usuario->puede('requerimientos.compra.crear'))
                        <a href="{{ route('requerimientos-compra.index') }}"
                            class="sidebar-link {{ request()->routeIs('requerimientos-compra.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="requisitions" :size="16" /></span>
                            <span>Requerimientos de compra</span>
                        </a>
                    @endif

                    @if ($proformasEnAlmacen && $usuario->puede('proformas.crear'))
                        <a href="{{ route('proformas.index') }}"
                            class="sidebar-link {{ request()->routeIs('proformas.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="quotes" :size="16" /></span>
                            <span>Proformas de venta directa</span>
                        </a>
                    @endif

                    @if ($esAlmacen)
                        <a href="{{ route('ordenes-operacion.index') }}"
                            class="sidebar-link {{ request()->routeIs('ordenes-operacion.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="orders" :size="16" /></span>
                            <span>Órdenes por atender</span>
                        </a>
                    @endif
                </div>
            </details>
        @endif
