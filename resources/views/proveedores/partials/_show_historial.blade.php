    <section class="panel supplier-history-panel">
        <header class="supplier-panel-heading">
            <div>
                <p class="eyebrow">Trazabilidad acumulada</p>
                <h2>Historial comercial</h2>
                <p>Consulta la actividad registrada y compara la evolución de precios por producto, moneda y fecha.</p>
            </div>
            <a href="{{ route('historial-precios.index', ['proveedor_id' => $proveedor->id]) }}"
                class="button button--ghost button--small">
                <x-ui.icon name="banknote" :size="17" />
                Consultar historial
            </a>
        </header>

        <dl class="supplier-history-summary">
            <div>
                <dt>Última cotización</dt>
                <dd>
                    {{ $resumen['ultima_cotizacion']
                        ? \Illuminate\Support\Carbon::parse($resumen['ultima_cotizacion'])->format('d/m/Y')
                        : 'Sin cotizaciones' }}
                </dd>
            </div>
            <div><dt>Cotizaciones vigentes</dt><dd>{{ $proveedor->cotizaciones_vigentes_count }}</dd></div>
            <div><dt>Productos cotizados</dt><dd>{{ $resumen['productos'] }}</dd></div>
            <div><dt>Órdenes de compra</dt><dd>{{ $proveedor->ordenes_compra_count }}</dd></div>
            <div><dt>Facturas registradas</dt><dd>{{ $proveedor->facturas_proveedor_count }}</dd></div>
        </dl>
    </section>
