@extends('layouts.app')

@section('title', 'Emitir orden de compra')
@section('page-kicker', 'Órdenes de compra')
@section('page-title', 'Emitir orden de compra')

@section('content')
    @php $cotizacion = $solicitud->cotizacion; @endphp
    <a href="{{ route('solicitudes-compra.show', $solicitud) }}" class="back-link"><x-ui.icon name="arrow-left" :size="17" /> Volver a la solicitud</a>

    <section class="module-header purchase-order-create-hero">
        <div>
            <p class="eyebrow">Registro de compra {{ $solicitud->codigo }}</p>
            <h1>Emitir orden para {{ $cotizacion?->proveedor?->nombreVisible() }}</h1>
            <p>Los productos, cantidades, precios, moneda y total están bloqueados por la decisión de Compras.</p>
        </div>
        <span class="badge badge--success">Origen validado</span>
    </section>

    <div class="purchase-approval-gate" role="note">
        <span><x-ui.icon name="info" :size="19" /></span>
        <div><strong>Sin recotización silenciosa</strong><p>Si cambia un producto, cantidad o precio, no debe alterarse aquí: corresponde registrar y aprobar una nueva cotización.</p></div>
    </div>

    <form method="POST" action="{{ route('ordenes-compra.store') }}" class="purchase-order-create-layout">
        @csrf
        <input type="hidden" name="solicitud_compra_id" value="{{ $solicitud->id }}">

        <section class="panel purchase-order-create-form">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Datos de emisión</p><h2>Fechas y condiciones</h2></div></header>
            <div class="form-grid form-grid--two">
                <label class="form-field"><span>Fecha de emisión *</span><input type="date" name="fecha_emision" required value="{{ old('fecha_emision', now()->toDateString()) }}"></label>
                <label class="form-field"><span>Entrega requerida</span><input type="date" name="fecha_entrega_requerida" value="{{ old('fecha_entrega_requerida') }}"></label>
                <label class="form-field form-field--wide"><span>Documento del proveedor</span><input type="text" name="numero_documento_proveedor" maxlength="60" value="{{ old('numero_documento_proveedor', $cotizacion?->numero_documento) }}"></label>
                <label class="form-field"><span>Condiciones de pago</span><textarea name="condiciones_pago" rows="3" maxlength="500">{{ old('condiciones_pago', $cotizacion?->condiciones_pago) }}</textarea></label>
                <label class="form-field"><span>Condiciones de entrega</span><textarea name="condiciones_entrega" rows="3" maxlength="500">{{ old('condiciones_entrega', $cotizacion?->condiciones_entrega) }}</textarea></label>
                <label class="form-field form-field--wide"><span>Observación de la orden</span><textarea name="observacion" rows="3" maxlength="500" placeholder="Indicaciones internas o de coordinación con el proveedor">{{ old('observacion') }}</textarea></label>
            </div>
        </section>

        <aside class="panel supplier-quote-total-card purchase-order-create-total">
            <p class="eyebrow">Importe autorizado</p>
            <div><span>Suma de líneas</span><strong><x-ui.money :value="$solicitud->total_lineas" :currency="$cotizacion?->moneda ?? 'PEN'" /></strong></div>
            @if ($solicitud->tieneAjusteRedondeo())
                <div><span>Ajuste documental</span><strong>{{ (float) $solicitud->ajuste_redondeo > 0 ? '+' : '−' }} <x-ui.money :value="abs((float) $solicitud->ajuste_redondeo)" :currency="$cotizacion?->moneda ?? 'PEN'" /></strong></div>
            @endif
            <div class="supplier-quote-total-card__main"><span>Total de la OC</span><strong><x-ui.money :value="$solicitud->total_seleccionado" :currency="$cotizacion?->moneda ?? 'PEN'" /></strong></div>
            <small>Aprobada por {{ $solicitud->aprobador?->nombreVisible() ?? $solicitud->solicitante?->nombreVisible() ?? 'Compras' }}{{ $solicitud->aprobado_en ? ' el '.$solicitud->aprobado_en->format('d/m/Y H:i') : '' }}.</small>
        </aside>

        <section class="panel purchase-order-source-lines">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Alcance bloqueado</p><h2>Productos autorizados</h2></div><span class="count-chip">{{ $solicitud->detalles->count() }}</span></header>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">Precio final unitario</th><th class="text-right">Importe</th><th>Observación</th></tr></thead>
                    <tbody>
                        @foreach ($solicitud->detalles as $detalle)
                            <tr>
                                <td><strong>{{ $detalle->producto?->codigo }}</strong><span>{{ $detalle->producto?->descripcion }}</span></td>
                                <td class="text-right"><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->producto?->unidadMedida?->abreviatura ?? 'und.' }}</td>
                                <td class="text-right"><x-ui.money :value="$detalle->precio_unitario" :currency="$cotizacion?->moneda ?? 'PEN'" /></td>
                                <td class="text-right"><strong><x-ui.money :value="$detalle->subtotal" :currency="$cotizacion?->moneda ?? 'PEN'" /></strong></td>
                                <td>{{ $detalle->observacion ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="form-actions purchase-order-create-actions">
            <a href="{{ route('solicitudes-compra.show', $solicitud) }}" class="button button--ghost">Cancelar</a>
            <button type="submit" class="button button--primary" data-confirm="¿Confirmas emitir la orden de compra con los importes aprobados?"><x-ui.icon name="purchase-order" :size="17" /> Emitir orden de compra</button>
        </div>
    </form>
@endsection
