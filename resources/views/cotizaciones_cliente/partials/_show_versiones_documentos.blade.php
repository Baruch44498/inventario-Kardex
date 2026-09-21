    <nav class="commercial-version-tabs" aria-label="Versiones de cotización">
        @foreach ($versiones as $version)
            <a href="{{ route('cotizaciones-cliente.show', $version) }}" class="{{ $version->is($cotizacion) ? 'is-active' : '' }}">
                VRS{{ $version->version }} <span>{{ $version->estadoVisual() }}</span>
            </a>
        @endforeach
    </nav>

    @if ($cotizacion->proforma)
        <x-ui.collapsible-notice title="Esta cotización no genera Orden de Venta" label="Ver información sobre el flujo de esta cotización">
            <span>Solo contiene las líneas marcadas como Venta en {{ $cotizacion->proforma->codigo }}. Los préstamos se controlan y reponen desde la Proforma.</span>
        </x-ui.collapsible-notice>
    @endif

    @if ($cotizacion->proforma && $cotizacion->estado === 'CERRADA')
        <section class="notice notice--success notice--block">
            <x-ui.icon name="check-circle" :size="20" />
            <div>
                <strong>Valorización lista para cobro</strong>
                <span>El total quedó cerrado. La integración con Contabilidad/Cuentas por cobrar se realizará en su módulo correspondiente.</span>
            </div>
        </section>
    @endif

    @if ($puedeAprobar || $puedeCerrarParaCobro || $puedeAnular)
        <section class="panel commercial-quote-actions">
            <header class="panel-heading">
                <p class="eyebrow">Control documental</p>
                <h2>Acciones de la cotización</h2>
                <p>{{ $cotizacion->proforma ? 'Cierra la valorización para cobro o anúlala conservando el historial.' : 'Aprueba el documento o anúlalo sin eliminar su historial.' }}</p>
            </header>

            <div class="commercial-quote-actions__grid">
                @if ($puedeCerrarParaCobro)
                    <article class="commercial-quote-action commercial-quote-action--approve">
                        <div>
                            <h3>Cerrar valorización para cobro</h3>
                            <p>Verifica precios, moneda e IGV. Al cerrar, esta versión quedará bloqueada y lista para Contabilidad.</p>
                        </div>
                        <form method="POST" action="{{ route('cotizaciones-cliente.cerrar', $cotizacion) }}" class="commercial-quote-action__form" data-confirm="¿Cerrar esta valorización y dejarla lista para cobro?">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="button button--primary">
                                <x-ui.icon name="check-circle" :size="17" /> Cerrar para cobro
                            </button>
                        </form>
                    </article>
                @endif

                @if ($puedeAprobar)
                    <article class="commercial-quote-action commercial-quote-action--approve">
                        <div>
                            <h3>Aprobar y generar la orden principal {{ $codigoTipoOrden }}</h3>
                            <p>
                                Sus áreas y materiales estimados quedarán congelados para compararlos con las salidas reales.
                                Solo los servicios internos HIDROIL generarán OS hijas. Esta acción no descuenta stock ni genera Kardex.
                            </p>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('cotizaciones-cliente.convertir-orden', $cotizacion) }}"
                            class="commercial-quote-action__form"
                            data-confirm="¿Aprobar esta cotización y generar su orden principal?"
                            data-confirm-title="Aprobar cotización"
                            data-confirm-label="Generar orden principal"
                            data-confirm-tone="info"
                        >
                            @csrf
                            <label class="form-field">
                                <span>Fecha de apertura <span class="required-mark">*</span></span>
                                <input type="date" name="fecha_apertura" value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required>
                            </label>
                            <button type="submit" class="button button--primary">
                                <x-ui.icon name="orders" :size="17" /> Generar orden principal
                            </button>
                        </form>
                    </article>
                @endif

                @if (($puedeAprobar || $puedeCerrarParaCobro) && $puedeAnular)
                    <div class="commercial-quote-actions__divider" aria-hidden="true"></div>
                @endif

                @if ($puedeAnular)
                    <article class="commercial-quote-action commercial-quote-action--cancel">
                        <div>
                            <h3>Anular esta versión</h3>
                            <p>La versión seguirá visible con usuario, fecha y motivo.</p>
                        </div>
                        <form
                            method="POST"
                            action="{{ route('cotizaciones-cliente.anular', $cotizacion) }}"
                            class="commercial-quote-action__form"
                            data-confirm="¿Confirmas anular esta versión?"
                            data-confirm-title="Anular cotización"
                            data-confirm-label="Anular versión"
                            data-confirm-tone="danger"
                        >
                            @csrf
                            @method('PATCH')
                            <label class="form-field">
                                <span>Motivo de anulación <span class="required-mark">*</span></span>
                                <input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required placeholder="Explica el motivo">
                            </label>
                            <button type="submit" class="button button--danger">
                                <x-ui.icon name="error" :size="17" /> Anular versión
                            </button>
                        </form>
                    </article>
                @endif
            </div>
        </section>
    @elseif ($cotizacion->ordenesOperacion->isNotEmpty())
        <section class="notice notice--success notice--block">
            <x-ui.icon name="check-circle" :size="20" />
            <div>
                <strong>Versión vinculada con {{ $cotizacion->ordenesOperacion->count() }} orden(es)</strong>
                <span>
                    Esta cotización originó:
                    @foreach ($cotizacion->ordenesOperacion as $ordenVinculada)
                        <a href="{{ route('ordenes-operacion.show', $ordenVinculada) }}">{{ $ordenVinculada->codigo_orden }}</a>{{ ! $loop->last ? ' · ' : '.' }}
                    @endforeach
                </span>
            </div>
        </section>
    @endif

    @if (auth()->user()->puede('proformas.cotizar') && $cotizacion->puedeCrearVersion() && $esUltima)
        <section class="commercial-action-panel commercial-action-panel--primary">
            <div><p class="eyebrow">Solicitud posterior</p><h2>Crear VRS{{ $cotizacion->version + 1 }}</h2><p>Se copiará esta versión y la nueva quedará abierta para modificarla.</p></div>
            <form method="POST" action="{{ route('cotizaciones-cliente.version', $cotizacion) }}">@csrf<button class="button button--ghost" type="submit"><x-ui.icon name="plus" :size="17" /> Nueva versión</button></form>
        </section>
    @endif

    @if ($cotizacion->estaAnulada())
        <section class="notice notice--danger notice--block commercial-cancelled"><x-ui.icon name="error" :size="20" /><div><strong>Versión anulada</strong><span>{{ $cotizacion->motivo_anulacion }} · {{ $cotizacion->anulado_en?->format('d/m/Y H:i') }}</span></div></section>
    @endif
