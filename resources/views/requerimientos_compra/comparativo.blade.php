@extends('layouts.app')

@section('title', 'Comparativo '.$requerimiento->codigo)
@section('page-kicker', 'Compras')
@section('page-title', 'Comparativo de proveedores')

@section('content')
    <a href="{{ route('requerimientos-compra.show', $requerimiento) }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver al requerimiento
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">{{ $requerimiento->codigo }} · decisión por producto</p>
            <h1>Comparar ofertas y dividir la compra</h1>
            <p>Elige una oferta por cada producto. Al confirmar, el sistema agrupa las líneas elegidas y genera una orden de compra independiente por cotización y proveedor.</p>
        </div>
        <div class="module-header__actions">
            <span class="badge badge--info">{{ $comparativo['lineas_cotizables'] }} por decidir</span>
            <span class="badge badge--success">{{ $comparativo['lineas_compradas'] }} compradas</span>
            @if ($comparativo['lineas_sin_oferta'] > 0)
                <span class="badge badge--warning">{{ $comparativo['lineas_sin_oferta'] }} sin oferta</span>
            @endif
        </div>
    </section>

    <x-ui.collapsible-notice title="Cómo leer la recomendación" label="Ver criterio del comparativo">
        <span>“Mejor precio” compara el importe final unitario convertido a soles. Es una ayuda: Logística puede elegir otra oferta por plazo, marca, garantía o condiciones comerciales.</span>
    </x-ui.collapsible-notice>

    @if ($errors->any())
        <div class="notice notice--danger notice--block" role="alert">
            <x-ui.icon name="error" :size="18" />
            <div><strong>No se pudo generar la compra.</strong><span>{{ $errors->first() }}</span></div>
        </div>
    @endif

    <form method="POST" action="{{ route('requerimientos-compra.comparativo.comprar', $requerimiento) }}" data-comparative-purchase-form data-confirm="¿Confirmas las ofertas ganadoras y la generación de las órdenes de compra?">
        @csrf

        @foreach ($comparativo['lineas'] as $linea)
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Producto solicitado</p>
                        <h2>{{ $linea['codigo'] }} — {{ $linea['descripcion'] }}</h2>
                        <p><x-ui.quantity :value="$linea['cantidad_solicitada']" /> {{ $linea['unidad'] }}</p>
                    </div>
                    @if ($linea['compra'])
                        <div>
                            <span class="badge badge--success">Compra generada</span>
                            <a href="{{ route('ordenes-compra.show', $linea['compra']->orden_id) }}" class="button button--ghost button--small">
                                {{ $linea['compra']->orden_codigo }}
                            </a>
                        </div>
                    @endif
                </div>

                @if ($linea['compra'])
                    <div class="notice notice--success notice--block">
                        <x-ui.icon name="check-circle" :size="18" />
                        <div>
                            <strong>{{ $linea['compra']->nombre_comercial ?: $linea['compra']->razon_social }}</strong>
                            <span>Esta línea ya está protegida contra una segunda compra mientras la OC permanezca vigente.</span>
                        </div>
                    </div>
                @elseif ($linea['ofertas']->isNotEmpty())
                    <div class="table-wrap table-wrap--wide">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Elegir</th>
                                    <th>Proveedor</th>
                                    <th>Producto ofrecido</th>
                                    <th>Cantidad</th>
                                    <th>Precio del documento</th>
                                    <th>Equivalente PEN</th>
                                    <th>Referencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($linea['ofertas'] as $oferta)
                                    <tr>
                                        <td>
                                            @if ($oferta['seleccionable'] && $puedeComprar)
                                                <input
                                                    type="radio"
                                                    name="selecciones[{{ $linea['requisicion_detalle_id'] }}]"
                                                    value="{{ $oferta['cotizacion_detalle_id'] }}"
                                                    data-comparative-choice
                                                    data-provider-id="{{ $oferta['proveedor_id'] }}"
                                                    @checked((string) old('selecciones.'.$linea['requisicion_detalle_id'], $linea['mejor_oferta_id']) === (string) $oferta['cotizacion_detalle_id'])
                                                    aria-label="Elegir oferta de {{ $oferta['proveedor'] }}"
                                                >
                                            @else
                                                <span class="text-muted">No disponible</span>
                                            @endif
                                        </td>
                                        <td><strong>{{ $oferta['proveedor'] }}</strong><span>{{ $oferta['cotizacion_codigo'] }}</span></td>
                                        <td>
                                            <strong>{{ $oferta['producto_codigo'] }}</strong>
                                            <span>{{ $oferta['producto_descripcion'] }}</span>
                                            @if ($oferta['tipo_vinculacion'] === 'ALTERNATIVA')
                                                <span class="badge badge--warning">Alternativa</span>
                                            @endif
                                        </td>
                                        <td><x-ui.quantity :value="$oferta['cantidad']" /></td>
                                        <td><x-ui.money :value="$oferta['precio_unitario']" :currency="$oferta['moneda']" /></td>
                                        <td>
                                            @if ($oferta['precio_pen'] !== null)
                                                <x-ui.money :value="$oferta['precio_pen']" currency="PEN" />
                                                @if ($oferta['es_mejor_precio'])
                                                    <span class="badge badge--success">Mejor precio</span>
                                                @endif
                                            @else
                                                <span class="badge badge--warning">Tipo de cambio pendiente</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('cotizaciones-proveedor.show', $oferta['cotizacion_id']) }}" class="button button--ghost button--small">Ver cotización</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="operation-embedded-empty operation-embedded-empty--wide">
                        <span class="operation-embedded-empty__icon"><x-ui.icon name="quotes" :size="25" /></span>
                        <strong>Este producto aún no tiene cotizaciones vinculadas</strong>
                        <span>Registra al menos una oferta antes de tomar la decisión de compra.</span>
                    </div>
                @endif
            </section>
        @endforeach

        @if ($puedeComprar && $comparativo['lineas_cotizables'] > 0)
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Confirmación</p>
                        <h2>Generar órdenes agrupadas</h2>
                        <p data-comparative-summary>Selecciona las ofertas que deseas comprar.</p>
                    </div>
                </div>
                <div class="form-grid form-grid--two">
                    <label class="form-field">
                        <span>Fecha de emisión *</span>
                        <input type="date" name="fecha_emision" required value="{{ old('fecha_emision', now()->toDateString()) }}">
                    </label>
                    <label class="form-field">
                        <span>Entrega requerida</span>
                        <input type="date" name="fecha_entrega_requerida" value="{{ old('fecha_entrega_requerida') }}">
                    </label>
                    <label class="form-field form-field--wide">
                        <span>Observación general</span>
                        <textarea name="observacion" rows="3" maxlength="500" placeholder="Criterio adicional o coordinación de la compra">{{ old('observacion') }}</textarea>
                    </label>
                </div>
                <div class="form-actions">
                    <a href="{{ route('requerimientos-compra.show', $requerimiento) }}" class="button button--ghost">Cancelar</a>
                    <button type="submit" class="button button--primary" data-comparative-submit>
                        <x-ui.icon name="purchase-order" :size="17" /> Generar OCs seleccionadas
                    </button>
                </div>
            </section>
        @elseif (! $puedeComprar)
            <div class="notice notice--info notice--block">
                <x-ui.icon name="info" :size="18" />
                <div><strong>Comparativo de consulta</strong><span>El requerimiento debe estar en estado Cotizando para registrar nuevas decisiones.</span></div>
            </div>
        @endif
    </form>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-comparative-purchase-form]');
    if (!form) return;

    const summary = form.querySelector('[data-comparative-summary]');
    const submit = form.querySelector('[data-comparative-submit]');
    const choices = Array.from(form.querySelectorAll('[data-comparative-choice]'));

    const sync = () => {
        const selected = choices.filter(choice => choice.checked);
        const providers = new Set(selected.map(choice => choice.dataset.providerId));
        if (summary) {
            summary.textContent = `${selected.length} producto${selected.length === 1 ? '' : 's'} seleccionado${selected.length === 1 ? '' : 's'} · ${providers.size} proveedor${providers.size === 1 ? '' : 'es'}`;
        }
        if (submit) submit.disabled = selected.length === 0;
    };

    choices.forEach(choice => choice.addEventListener('change', sync));
    sync();
})();
</script>
@endpush
