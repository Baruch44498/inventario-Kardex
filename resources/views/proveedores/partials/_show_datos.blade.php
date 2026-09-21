    <section class="summary-strip summary-strip--four">
        @foreach ([
            ['Cotizaciones', 'quotes', 'info', $proveedor->cotizaciones_vigentes_count],
            ['Productos cotizados', 'products', 'warning', $resumen['productos']],
            ['Órdenes de compra', 'purchase-order', 'success', $proveedor->ordenes_compra_count],
            ['Facturas', 'invoice', 'neutral', $proveedor->facturas_proveedor_count],
        ] as [$titulo, $icono, $tono, $valor])
            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--{{ $tono }}">
                    <x-ui.icon :name="$icono" :size="21" />
                </span>
                <div><span>{{ $titulo }}</span><strong>{{ $valor }}</strong></div>
            </article>
        @endforeach
    </section>

    <section class="supplier-detail-grid supplier-detail-grid--single">
        <article class="panel supplier-info-panel">
            <header class="supplier-panel-heading">
                <div>
                    <p class="eyebrow">Información principal</p>
                    <h2>Datos comerciales</h2>
                </div>
            </header>

            <dl class="supplier-info-grid">
                <div><dt>Contacto</dt><dd>{{ $proveedor->contacto ?: 'No registrado' }}</dd></div>
                <div><dt>Teléfono</dt><dd>{{ $proveedor->telefono ?: 'No registrado' }}</dd></div>
                <div><dt>Correo</dt><dd>{{ $proveedor->correo ?: 'No registrado' }}</dd></div>
                <div><dt>Ubicación</dt><dd>{{ $proveedor->ubicacionVisible() ?: 'No registrada' }}</dd></div>
                <div class="supplier-info-grid__wide">
                    <dt>Dirección</dt><dd>{{ $proveedor->direccion ?: 'No registrada' }}</dd>
                </div>
                <div>
                    <dt>Última cotización</dt>
                    <dd>
                        {{ $resumen['ultima_cotizacion']
                            ? \Illuminate\Support\Carbon::parse($resumen['ultima_cotizacion'])->format('d/m/Y')
                            : 'Sin cotizaciones' }}
                    </dd>
                </div>
            </dl>
        </article>
    </section>
