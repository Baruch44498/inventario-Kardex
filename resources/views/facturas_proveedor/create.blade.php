@extends('layouts.app')

@section('title', 'Registrar factura de proveedor')
@section('page-kicker', 'Recepción documental')
@section('page-title', 'Registrar factura de proveedor')

@section('content')
    <a href="{{ route('ordenes-compra.show', $orden) }}" class="back-link"><x-ui.icon name="arrow-left" :size="17" /> Volver a {{ $orden->codigo }}</a>

    <section class="module-header supplier-invoice-create-header">
        <div>
            <p class="eyebrow">Documento físico recibido</p>
            <h1>Registrar factura</h1>
            <p>La factura se conciliará con recepciones confirmadas. Productos, precios, moneda e IGV se heredan de la OC aprobada.</p>
        </div>
        <span class="badge badge--info">OC {{ $orden->codigo }}</span>
    </section>

    @if ($errors->any())
        <div class="notice notice--danger notice--block" role="alert"><x-ui.icon name="error" :size="18" /><div><strong>Revisa la factura antes de guardarla.</strong><span>{{ $errors->first() }}</span></div></div>
    @endif

    <section class="order-context-card supplier-invoice-order-context">
        <div class="order-context-card__main"><span class="order-context-card__icon"><x-ui.icon name="purchase-order" :size="25" /></span><div><span>Orden vinculada</span><strong>{{ $orden->codigo }}</strong><small>{{ $orden->proveedor?->nombreVisible() }} · RUC {{ $orden->proveedor?->ruc }}</small></div></div>
        <dl class="order-context-card__facts"><div><dt>Moneda</dt><dd>{{ $orden->moneda }}</dd></div><div><dt>Total autorizado</dt><dd><x-ui.money :value="$orden->total" :currency="$orden->moneda" /></dd></div><div><dt>Estado</dt><dd>{{ $orden->estadoVisible() }}</dd></div></dl>
    </section>

    <form method="POST" action="{{ route('facturas-proveedor.store') }}" enctype="multipart/form-data" class="supplier-invoice-form" data-supplier-invoice-form data-dirty-form data-loading-form>
        @csrf
        <input type="hidden" name="orden_compra_id" value="{{ $orden->id }}">

        <section class="panel form-panel">
            <div class="panel-heading"><p class="eyebrow">Identificación fiscal</p><h2>Datos del documento</h2><p>Completa únicamente los datos que identifican el comprobante recibido.</p></div>
            <div class="form-grid form-grid--three">
                <label class="form-field"><span>Tipo de documento *</span><select name="tipo_documento" required><option value="FACTURA" @selected(old('tipo_documento', 'FACTURA') === 'FACTURA')>Factura</option><option value="BOLETA" @selected(old('tipo_documento') === 'BOLETA')>Boleta</option></select></label>
                <label class="form-field"><span>Serie *</span><input type="text" name="serie" value="{{ old('serie') }}" maxlength="20" placeholder="F001" required></label>
                <label class="form-field"><span>Número *</span><input type="text" name="numero" value="{{ old('numero') }}" maxlength="30" placeholder="00001234" required></label>
                <label class="form-field"><span>Fecha de emisión *</span><input type="date" name="fecha_emision" value="{{ old('fecha_emision', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label>
                <label class="form-field"><span>Fecha de vencimiento</span><input type="date" name="fecha_vencimiento" value="{{ old('fecha_vencimiento') }}"></label>
                <div class="form-field"><span>Moneda y tipo de cambio</span><div class="readonly-field"><strong>{{ $orden->moneda }}@if($orden->moneda === 'USD') · TC {{ number_format((float) $orden->tipo_cambio, 2) }}@endif</strong><small>Protegidos por la OC aprobada</small></div></div>
                <label class="form-field form-field--span-2"><span>Documento original *</span><input type="file" name="archivo_original" accept=".pdf,.jpg,.jpeg,.png" required><small>PDF digital o imagen legible. Máximo 15 MB.</small></label>
                <label class="form-field form-field--span-3"><span>Observación</span><textarea name="observacion" rows="2" maxlength="500" placeholder="Referencia o información adicional">{{ old('observacion') }}</textarea></label>
            </div>
        </section>

        <section class="panel supplier-invoice-lines-panel">
            <div class="panel-heading">
                <p class="eyebrow">Conciliación protegida</p>
                <h2>Productos recibidos pendientes de facturar</h2>
                <p>La cantidad puede reducirse para registrar una factura parcial, pero nunca superar lo recibido. El servidor obtiene producto, costo e IGV directamente de la OC.</p>
            </div>
            <div class="notice notice--info notice--block"><x-ui.icon name="lock" :size="18" /><div><strong>Importes no editables</strong><span>Si el comprobante físico difiere de la OC, no alteres la línea: revisa primero la cotización u orden con Logística.</span></div></div>
            @error('detalles')<div class="notice notice--danger notice--block">{{ $message }}</div>@enderror
            <div class="table-wrap table-wrap--wide">
                <table class="data-table supplier-invoice-lines-table">
                    <thead><tr><th>Producto</th><th>Recepción</th><th class="text-right">Saldo recibido</th><th>Cantidad facturada</th><th class="text-right">Costo unitario autorizado</th><th>IGV</th><th class="text-right">Base</th><th class="text-right">IGV</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach ($filas as $indice => $fila)
                            @php
                                $detalle = $fila['detalle'];
                                $nota = $fila['nota'];
                                $ingresoDetalle = $fila['ingreso_detalle'];
                                $cantidad = old("detalles.{$indice}.cantidad", $fila['pendiente']);
                                $afecto = (bool) $fila['afecto_igv_default'];
                            @endphp
                            <tr data-invoice-row data-unit-cost="{{ (float) $fila['costo_total_default'] }}" data-taxed="{{ $afecto ? '1' : '0' }}">
                                <td><input type="hidden" name="detalles[{{ $indice }}][orden_compra_detalle_id]" value="{{ $detalle->id }}"><input type="hidden" name="detalles[{{ $indice }}][nota_ingreso_detalle_id]" value="{{ $ingresoDetalle->id }}"><strong>{{ $detalle->producto?->codigo }}</strong><span>{{ $detalle->producto?->descripcion }}</span><small>{{ $detalle->producto?->unidadMedida?->codigo ?? 'UND' }}</small></td>
                                <td><strong>{{ $nota->codigo }}</strong><span>{{ $nota->fecha_ingreso?->format('d/m/Y') }}</span>@error("detalles.{$indice}.nota_ingreso_detalle_id")<small class="field-error">{{ $message }}</small>@enderror</td>
                                <td class="text-right"><strong><x-ui.quantity :value="$fila['pendiente']" /></strong></td>
                                <td><input class="table-input" type="number" name="detalles[{{ $indice }}][cantidad]" value="{{ $cantidad }}" min="0" max="{{ $fila['pendiente'] }}" step="0.001" data-invoice-quantity><small>Máximo recibido</small>@error("detalles.{$indice}.cantidad")<small class="field-error">{{ $message }}</small>@enderror</td>
                                <td class="text-right"><strong><x-ui.money :value="$fila['costo_total_default']" :currency="$orden->moneda" /></strong><small>Según OC</small></td>
                                <td><span class="badge badge--{{ $afecto ? 'info' : 'neutral' }}">{{ $afecto ? '18% incluido' : 'No aplica' }}</span></td>
                                <td class="text-right" data-invoice-base>—</td><td class="text-right" data-invoice-igv>—</td><td class="text-right"><strong data-invoice-total>—</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel supplier-invoice-totals-panel">
            <div><p class="eyebrow">Totales calculados</p><h2>Resultado de la conciliación</h2><p>Estos importes se recalculan desde la OC según las cantidades facturadas y no se envían como campos editables.</p></div>
            <dl class="order-context-card__facts">
                <div><dt>Base imponible</dt><dd><span data-document-base>0.00</span> {{ $orden->moneda }}</dd></div>
                <div><dt>IGV / crédito fiscal</dt><dd><span data-document-igv>0.00</span> {{ $orden->moneda }}</dd></div>
                <div><dt>Total conciliado</dt><dd><strong><span data-document-total>0.00</span> {{ $orden->moneda }}</strong></dd></div>
            </dl>
        </section>

        <div class="form-actions form-actions--sticky"><a href="{{ route('ordenes-compra.show', $orden) }}" class="button button--ghost">Cancelar</a><button type="submit" class="button button--primary" data-submit-button data-loading-text="Registrando factura..."><x-ui.icon name="invoice" :size="17" /><span data-submit-label>Registrar factura</span></button></div>
    </form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-supplier-invoice-form]');
    if (!form) return;
    const money = (value) => Number(value || 0).toFixed(2);
    const calculate = () => {
        let base = 0, igv = 0, total = 0;
        form.querySelectorAll('[data-invoice-row]').forEach((row) => {
            const quantity = Number(row.querySelector('[data-invoice-quantity]')?.value || 0);
            const cost = Number(row.dataset.unitCost || 0);
            const lineTotal = quantity * cost;
            const lineBase = row.dataset.taxed === '1' ? lineTotal / 1.18 : lineTotal;
            const lineIgv = lineTotal - lineBase;
            base += lineBase;
            igv += lineIgv;
            total += lineTotal;
            row.querySelector('[data-invoice-base]').textContent = money(lineBase);
            row.querySelector('[data-invoice-igv]').textContent = money(lineIgv);
            row.querySelector('[data-invoice-total]').textContent = money(lineTotal);
        });
        form.querySelector('[data-document-base]').textContent = money(base);
        form.querySelector('[data-document-igv]').textContent = money(igv);
        form.querySelector('[data-document-total]').textContent = money(total);
    };
    form.querySelectorAll('[data-invoice-quantity]').forEach((input) => input.addEventListener('input', calculate));
    calculate();
});
</script>
@endpush
