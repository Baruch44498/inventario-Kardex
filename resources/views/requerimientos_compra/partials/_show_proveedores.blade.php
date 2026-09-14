@if ($detallesPendientesCotizar->isNotEmpty())
    <section class="panel purchase-requirement-contacts-panel">
        <div class="panel-heading purchase-requirement-section-heading">
            <div class="purchase-requirement-section-heading__copy">
                <p class="eyebrow">Cobertura automática</p>
                <div class="purchase-requirement-section-heading__title-row">
                    <h2>Distribución sugerida por proveedor</h2>
                </div>
                <p class="purchase-requirement-section-heading__description">Cada producto aparece una sola vez. Se prioriza al proveedor histórico que cubre más productos pendientes y luego la cotización más reciente.</p>
            </div>
            <div class="purchase-requirement-section-heading__meta">
                @if ($coberturaTotal && $gruposSugeridos->count() === 1)
                    <span class="badge badge--success">Un proveedor cubre los {{ $detallesPendientesCotizar->count() }} pendientes</span>
                @elseif ($coberturaTotal)
                    <span class="badge badge--info">{{ $detallesPendientesCotizar->count() }} pendientes en {{ $gruposSugeridos->count() }} listas</span>
                @else
                    <span class="badge badge--warning">{{ $detallesPendientesCotizar->count() }} pendientes · cobertura parcial</span>
                @endif
            </div>
        </div>

        @if ($gruposSugeridos->isNotEmpty() || $productosSinProveedor->isNotEmpty())
            <div class="purchase-requirement-contact-grid">
                @foreach ($gruposSugeridos as $grupo)
                    @include('requerimientos_compra.partials._tarjeta_proveedor', [
                        'tipo' => 'distribucion',
                        'registro' => $grupo,
                        'numeroLista' => $loop->iteration,
                    ])
                @endforeach

                @if ($productosSinProveedor->isNotEmpty())
                    <article class="prc-supplier-card prc-supplier-card--pending">
                        <div class="prc-supplier-card__header">
                            <div class="prc-supplier-avatar prc-supplier-avatar--pending" aria-hidden="true">?</div>
                            <div class="prc-supplier-card__identity">
                                <strong class="prc-supplier-card__name">Proveedor por definir</strong>
                                <span class="prc-supplier-card__ruc">Sin historial de cotizaciones</span>
                            </div>
                            <span class="prc-supplier-card__badge badge badge--warning">
                                {{ $productosSinProveedor->count() }}&nbsp;pendiente{{ $productosSinProveedor->count() === 1 ? '' : 's' }}
                            </span>
                        </div>
                        <div class="prc-supplier-card__body">
                            <p class="prc-supplier-card__label">Productos sin cobertura</p>
                            <div class="prc-product-chips">
                                @foreach ($productosSinProveedor as $producto)
                                    <span class="prc-product-chip prc-product-chip--pending">{{ Str::before($producto, ' —') }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="prc-supplier-card__footer">
                            <p class="prc-supplier-card__hint">
                                <x-ui.icon name="info" :size="13" />
                                Logística debe seleccionar el proveedor manualmente al cotizar.
                            </p>
                        </div>
                    </article>
                @endif
            </div>
        @else
            <div class="operation-embedded-empty operation-embedded-empty--wide">
                <span class="operation-embedded-empty__icon"><x-ui.icon name="suppliers" :size="25" /></span>
                <strong>No hay productos para distribuir</strong>
            </div>
        @endif
    </section>

    <section class="panel purchase-requirement-contacts-panel">
        <div class="panel-heading purchase-requirement-section-heading">
            <div class="purchase-requirement-section-heading__copy">
                <p class="eyebrow">Apoyo para Logística</p>
                <div class="purchase-requirement-section-heading__title-row">
                    <h2>Contactos de proveedores sugeridos</h2>
                </div>
                <p class="purchase-requirement-section-heading__description">Se obtienen de cotizaciones de proveedor registradas anteriormente para los productos de este requerimiento.</p>
            </div>
            <div class="purchase-requirement-section-heading__meta" aria-label="Cantidad de proveedores sugeridos">
                @if ($puedeGestionar && in_array($requerimiento->estado, ['ENVIADA', 'EN_REVISION', 'COTIZANDO'], true))
                    <a href="{{ route('cotizaciones-proveedor.create', [
                        'requisicion_id' => $requerimiento->id,
                        'detalle_ids' => $detallesPendientesCotizar->pluck('id')->all(),
                    ]) }}" class="button button--primary button--small">
                        <x-ui.icon name="quotes" :size="16" /> Registrar cotización
                    </a>
                @endif
                <span class="count-chip">
                    {{ $contactos->count() }} proveedor{{ $contactos->count() === 1 ? '' : 'es' }}
                </span>
            </div>
        </div>

        @if ($contactos->isNotEmpty())
            <div class="purchase-requirement-contact-grid">
                @foreach ($contactos as $contacto)
                    @include('requerimientos_compra.partials._tarjeta_proveedor', [
                        'tipo' => 'contacto',
                        'registro' => $contacto,
                    ])
                @endforeach
            </div>
        @else
            <div class="operation-embedded-empty operation-embedded-empty--wide">
                <span class="operation-embedded-empty__icon"><x-ui.icon name="suppliers" :size="25" /></span>
                <strong>No hay proveedores históricos para estos productos</strong>
                <span>Logística puede buscar un proveedor en el catálogo y, cuando registre su primera cotización, quedará relacionado históricamente con el producto.</span>
            </div>
        @endif
    </section>
@else
    <div class="notice notice--success notice--block" role="status">
        <x-ui.icon name="check-circle" :size="19" />
        <div>
            <strong>Todos los productos ya cuentan con una cotización</strong>
            <p>No se muestran nuevas solicitudes a proveedores. Continúa con la comparación de ofertas y la generación de órdenes de compra.</p>
        </div>
    </div>
@endif
