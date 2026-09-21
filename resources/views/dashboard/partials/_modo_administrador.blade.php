        <section class="metric-grid" aria-label="Resumen general del sistema">
            @foreach ([
                ['Usuarios activos', 'users', 'info', $resumen['usuarios_activos'], 'Accesos habilitados'],
                ['Clientes activos', 'users', 'success', $resumen['clientes_activos'], 'Catálogo comercial'],
                ['Proveedores activos', 'suppliers', 'success', $resumen['proveedores_activos'], 'Catálogo de compras'],
                ['Productos activos', 'products', 'info', $resumen['productos_activos'], 'Catálogo de Almacén'],
                ['Cotizaciones abiertas', 'quotes', 'warning', $resumen['cotizaciones_abiertas'], 'Pendientes de cierre'],
                ['Órdenes en curso', 'orders', 'warning', $resumen['ordenes_en_curso'], 'Abiertas o en proceso'],
            ] as [$titulo, $icono, $tono, $valor, $detalle])
                <article class="metric-card metric-card--{{ $tono }}">
                    <div class="metric-card__top">
                        <span class="metric-card__label">{{ $titulo }}</span>
                        <span class="metric-card__icon"><x-ui.icon :name="$icono" :size="22" /></span>
                    </div>
                    <strong>{{ number_format($valor) }}</strong>
                    <small>{{ $detalle }}</small>
                </article>
            @endforeach
        </section>

        <section class="dashboard-grid admin-area-grid" aria-label="Módulos organizados por área">
            <article class="panel admin-area-card">
                <header class="panel__header">
                    <div><p class="eyebrow">Área comercial</p><h2>Comercial y logística</h2></div>
                </header>
                <div class="admin-area-links">
                    <a href="{{ route('clientes.index') }}" class="role-quick-card"><span><x-ui.icon name="users" :size="22" /></span><div><strong>Clientes</strong><small>Datos, tipos, direcciones y vehículos.</small></div></a>
                    <a href="{{ route('cotizaciones-cliente.index') }}" class="role-quick-card"><span><x-ui.icon name="quotes" :size="22" /></span><div><strong>Cotizaciones al cliente</strong><small>Directas y originadas en Almacén.</small></div></a>
                    <a href="{{ route('ordenes-operacion.index') }}" class="role-quick-card"><span><x-ui.icon name="orders" :size="22" /></span><div><strong>Órdenes OM, OS y OP</strong><small>Órdenes vinculadas con su cotización.</small></div></a>
                </div>
            </article>

            <article class="panel admin-area-card">
                <header class="panel__header">
                    <div><p class="eyebrow">Área de abastecimiento</p><h2>Compras y proveedores</h2></div>
                </header>
                <div class="admin-area-links">
                    <a href="{{ route('proveedores.index') }}" class="role-quick-card"><span><x-ui.icon name="suppliers" :size="22" /></span><div><strong>Proveedores</strong><small>Catálogo y datos comerciales.</small></div></a>
                    <a href="{{ route('requerimientos-compra.index') }}" class="role-quick-card"><span><x-ui.icon name="requisitions" :size="22" /></span><div><strong>Requerimientos de compra</strong><small>Necesidades enviadas por Almacén.</small></div></a>
                    <a href="{{ route('cotizaciones-proveedor.index') }}" class="role-quick-card"><span><x-ui.icon name="quotes" :size="22" /></span><div><strong>Cotizaciones de proveedores</strong><small>Precios, IGV, moneda y descuentos.</small></div></a>
                    <a href="{{ route('historial-precios.index') }}" class="role-quick-card"><span><x-ui.icon name="banknote" :size="22" /></span><div><strong>Historial de precios</strong><small>Comparación por producto y proveedor.</small></div></a>
                    <a href="{{ route('ordenes-compra.index') }}" class="role-quick-card"><span><x-ui.icon name="purchase-order" :size="22" /></span><div><strong>Órdenes de compra</strong><small>Compras autorizadas y seguimiento.</small></div></a>
                </div>
            </article>

            <article class="panel admin-area-card">
                <header class="panel__header">
                    <div><p class="eyebrow">Área operativa</p><h2>Almacén</h2></div>
                </header>
                <div class="admin-area-links">
                    <a href="{{ route('productos.index') }}" class="role-quick-card"><span><x-ui.icon name="products" :size="22" /></span><div><strong>Productos</strong><small>Catálogo, unidades y marcas.</small></div></a>
                    <a href="{{ route('inventario.index') }}" class="role-quick-card"><span><x-ui.icon name="inventory" :size="22" /></span><div><strong>Inventario</strong><small>Existencias y niveles de stock.</small></div></a>
                    <a href="{{ route('proformas.index') }}" class="role-quick-card"><span><x-ui.icon name="quotes" :size="22" /></span><div><strong>Proformas de venta directa</strong><small>Ventas y préstamos enviados a Logística para su valorización.</small></div></a>
                    <a href="{{ route('notas-ingreso.index') }}" class="role-quick-card"><span><x-ui.icon name="entry" :size="22" /></span><div><strong>Notas de ingreso</strong><small>Entradas confirmadas al inventario.</small></div></a>
                    <a href="{{ route('notas-salida.index') }}" class="role-quick-card"><span><x-ui.icon name="exit" :size="22" /></span><div><strong>Notas de salida</strong><small>Despachos asociados a órdenes.</small></div></a>
                    <a href="{{ route('alertas.index') }}" class="role-quick-card"><span><x-ui.icon name="alerts" :size="22" /></span><div><strong>Alertas de stock</strong><small>Faltantes y niveles mínimos.</small></div></a>
                </div>
            </article>

            <article class="panel admin-area-card">
                <header class="panel__header">
                    <div><p class="eyebrow">Área de ejecución</p><h2>Control de planta</h2></div>
                </header>
                <div class="admin-area-links">
                    <a href="{{ route('ordenes-operacion.index', ['estado' => 'ACTIVAS']) }}" class="role-quick-card"><span><x-ui.icon name="activity" :size="22" /></span><div><strong>Órdenes activas y avance</strong><small>Materiales, ejecución y cierre en una sola pantalla.</small></div></a>
                </div>
            </article>

            <article class="panel admin-area-card">
                <header class="panel__header">
                    <div><p class="eyebrow">Área financiera</p><h2>Contabilidad</h2></div>
                </header>
                <div class="admin-area-links">
                    <a href="{{ route('modulos.show', 'cuentas-cobrar') }}" class="role-quick-card"><span><x-ui.icon name="invoice" :size="22" /></span><div><strong>Cuentas por cobrar</strong><small>Ventas y servicios finalizados.</small></div></a>
                    <a href="{{ route('facturas-proveedor.index') }}" class="role-quick-card"><span><x-ui.icon name="coins" :size="22" /></span><div><strong>Facturas por pagar</strong><small>Documentos fiscales y recepción de proveedores.</small></div></a>
                </div>
            </article>

            <article class="panel admin-area-card">
                <header class="panel__header">
                    <div><p class="eyebrow">Supervisión general</p><h2>Administración del sistema</h2></div>
                </header>
                <div class="admin-area-links">
                    <a href="{{ route('usuarios.index') }}" class="role-quick-card"><span><x-ui.icon name="users" :size="22" /></span><div><strong>Usuarios y permisos</strong><small>Roles definitivos y accesos.</small></div></a>
                    <a href="{{ route('kardex.index') }}" class="role-quick-card"><span><x-ui.icon name="coins" :size="22" /></span><div><strong>Kardex valorizado</strong><small>Consulta valorizada del inventario.</small></div></a>
                    <a href="{{ route('modulos.show', 'auditoria') }}" class="role-quick-card"><span><x-ui.icon name="clipboard" :size="22" /></span><div><strong>Auditoría</strong><small>Trazabilidad y control del sistema.</small></div></a>
                </div>
            </article>
        </section>
