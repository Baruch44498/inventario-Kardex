@extends('layouts.app')

@section('title', 'Registrar factura de proveedor')
@section('page-kicker', 'Recepción documental')
@section('page-title', 'Registrar factura de proveedor')

@section('content')
    <a href="{{ route('ordenes-compra.show', $orden) }}" class="back-link"><x-ui.icon name="arrow-left" :size="17" /> Volver a {{ $orden->codigo }}</a>

    <section class="module-header supplier-invoice-create-header">
        <div><p class="eyebrow">Documento físico recibido</p><h1>Registrar factura</h1><p>{{ $modoRecepcion ? 'La factura se conciliará con una recepción confirmada.' : 'La mercadería aún no fue recibida: Almacén vinculará esta factura al confirmar la Nota de Ingreso.' }} Para el Kardex, el sistema valoriza el costo total con IGV y lo convierte automáticamente a soles.</p></div>
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
        <input type="hidden" name="moneda" value="{{ $orden->moneda }}">

        <section class="panel form-panel">
            <div class="panel-heading"><p class="eyebrow">Identificación fiscal</p><h2>Datos del documento</h2></div>
            <div class="form-grid form-grid--three">
                <label class="form-field"><span>Tipo de documento *</span><select name="tipo_documento" required><option value="FACTURA" @selected(old('tipo_documento', 'FACTURA') === 'FACTURA')>Factura</option><option value="BOLETA" @selected(old('tipo_documento') === 'BOLETA')>Boleta</option></select></label>
                <label class="form-field"><span>Serie *</span><input type="text" name="serie" value="{{ old('serie') }}" maxlength="20" placeholder="F001" required></label>
                <label class="form-field"><span>Número *</span><input type="text" name="numero" value="{{ old('numero') }}" maxlength="30" placeholder="00001234" required></label>
                <label class="form-field"><span>Fecha de emisión *</span><input type="date" name="fecha_emision" value="{{ old('fecha_emision', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label>
                <label class="form-field"><span>Fecha de vencimiento</span><input type="date" name="fecha_vencimiento" value="{{ old('fecha_vencimiento') }}"></label>
                <div class="form-field"><span>Moneda</span><div class="readonly-field"><strong>{{ $orden->moneda }}</strong><small>Debe coincidir con la OC</small></div></div>
                @if ($orden->moneda === 'USD')
                    <label class="form-field"><span>Tipo de cambio *</span><input type="number" name="tipo_cambio" value="{{ old('tipo_cambio', $orden->tipo_cambio) }}" min="0.000001" step="0.000001" required><small>Usado para valorizar el inventario en soles.</small></label>
                @endif
                <label class="form-field form-field--span-2"><span>Documento original *</span><input type="file" name="archivo_original" accept=".pdf,.jpg,.jpeg,.png" required><small>PDF digital o imagen legible. Máximo 15 MB.</small></label>
                <label class="form-field form-field--span-3"><span>Observación</span><textarea name="observacion" rows="2" maxlength="500" placeholder="Referencia, condición o información adicional">{{ old('observacion') }}</textarea></label>
            </div>
        </section>

        <section class="panel supplier-invoice-lines-panel">
            <div class="panel-heading"><p class="eyebrow">Detalle fiscal</p><h2>{{ $modoRecepcion ? 'Productos recibidos por conciliar' : 'Productos facturados pendientes de recepción' }}</h2><p>{{ $modoRecepcion ? 'Cada línea corresponde a una recepción confirmada de Almacén.' : 'Las cantidades se controlan contra la OC; todavía no se modifica el inventario.' }} Ingresa el costo unitario total tal como se paga, con IGV incluido cuando la línea esté afecta.</p></div>
            @error('detalles')<div class="notice notice--danger notice--block">{{ $message }}</div>@enderror
            <div class="table-wrap table-wrap--wide">
                <table class="data-table supplier-invoice-lines-table">
                    <thead><tr><th>Producto</th>@if($modoRecepcion)<th>Recepción</th>@endif<th class="text-right">{{ $modoRecepcion ? 'Recibido pendiente' : 'Pendiente OC' }}</th><th>Cantidad facturada</th><th>Costo unitario total</th><th>IGV 18%</th><th class="text-right">Base</th><th class="text-right">IGV</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach ($filas as $indice => $fila)
                            @php
                                $detalle = $fila['detalle'];
                                $nota = $fila['nota'];
                                $ingresoDetalle = $fila['ingreso_detalle'];
                                $cantidad = old("detalles.{$indice}.cantidad", $fila['pendiente']);
                                $costo = old("detalles.{$indice}.costo_unitario_total", $fila['costo_total_default']);
                                $afecto = (bool) old("detalles.{$indice}.afecto_igv", $fila['afecto_igv_default']);
                            @endphp
                            <tr data-invoice-row>
                                <td><input type="hidden" name="detalles[{{ $indice }}][orden_compra_detalle_id]" value="{{ $detalle->id }}">@if($modoRecepcion)<input type="hidden" name="detalles[{{ $indice }}][nota_ingreso_detalle_id]" value="{{ $ingresoDetalle->id }}">@endif<input type="hidden" name="detalles[{{ $indice }}][producto_id]" value="{{ $detalle->producto_id }}"><strong>{{ $detalle->producto?->codigo }}</strong><span>{{ $detalle->producto?->descripcion }}</span><small>{{ $detalle->producto?->unidadMedida?->codigo ?? 'UND' }}</small></td>
                                @if($modoRecepcion)<td><strong>{{ $nota->codigo }}</strong><span>{{ $nota->fecha_ingreso?->format('d/m/Y') }}</span>@error("detalles.{$indice}.nota_ingreso_detalle_id")<small class="field-error">{{ $message }}</small>@enderror</td>@endif
                                <td class="text-right"><strong><x-ui.quantity :value="$fila['pendiente']" /></strong></td>
                                <td><input class="table-input" type="number" name="detalles[{{ $indice }}][cantidad]" value="{{ $cantidad }}" min="0" max="{{ $fila['pendiente'] }}" step="0.001" data-invoice-quantity>@error("detalles.{$indice}.cantidad")<small class="field-error">{{ $message }}</small>@enderror</td>
                                <td><input class="table-input" type="number" name="detalles[{{ $indice }}][costo_unitario_total]" value="{{ $costo }}" min="0" step="0.0001" data-invoice-cost>@error("detalles.{$indice}.costo_unitario_total")<small class="field-error">{{ $message }}</small>@enderror</td>
                                <td><input type="hidden" name="detalles[{{ $indice }}][afecto_igv]" value="0"><label class="invoice-tax-toggle"><input type="checkbox" name="detalles[{{ $indice }}][afecto_igv]" value="1" data-invoice-tax @checked($afecto)><span>Afecto</span></label></td>
                                <td class="text-right" data-invoice-base>—</td><td class="text-right" data-invoice-igv>—</td><td class="text-right"><strong data-invoice-total>—</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel supplier-invoice-totals-panel">
            <div><p class="eyebrow">Totales del documento</p><h2>Conciliar con la factura física</h2><p>Puedes corregir hasta cinco céntimos por redondeo. Una diferencia mayor debe revisarse en las líneas.</p></div>
            <div class="supplier-invoice-totals-grid">
                <label><span>Base imponible</span><input type="number" name="subtotal_documento" value="{{ old('subtotal_documento') }}" min="0" step="0.01" data-document-base required></label>
                <label><span>IGV / crédito fiscal</span><input type="number" name="impuesto_documento" value="{{ old('impuesto_documento') }}" min="0" step="0.01" data-document-igv required></label>
                <label class="supplier-invoice-total-main"><span>Total pagado</span><input type="number" name="total_documento" value="{{ old('total_documento') }}" min="0.01" step="0.01" data-document-total required></label>
                <button type="button" class="button button--ghost button--small" data-use-calculated-totals>Usar totales calculados</button>
            </div>
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
    const calculate = (writeDocument = false) => {
        let base = 0, igv = 0, total = 0;
        form.querySelectorAll('[data-invoice-row]').forEach((row) => {
            const quantity = Number(row.querySelector('[data-invoice-quantity]')?.value || 0);
            const cost = Number(row.querySelector('[data-invoice-cost]')?.value || 0);
            const lineTotal = quantity * cost;
            const taxed = row.querySelector('[data-invoice-tax]')?.checked;
            const lineBase = taxed ? lineTotal / 1.18 : lineTotal;
            const lineIgv = lineTotal - lineBase;
            base += lineBase; igv += lineIgv; total += lineTotal;
            row.querySelector('[data-invoice-base]').textContent = money(lineBase);
            row.querySelector('[data-invoice-igv]').textContent = money(lineIgv);
            row.querySelector('[data-invoice-total]').textContent = money(lineTotal);
        });
        if (writeDocument) {
            form.querySelector('[data-document-base]').value = money(base);
            form.querySelector('[data-document-igv]').value = money(igv);
            form.querySelector('[data-document-total]').value = money(total);
        }
    };
    form.querySelectorAll('[data-invoice-quantity], [data-invoice-cost], [data-invoice-tax]').forEach((input) => input.addEventListener('input', () => calculate(true)));
    form.querySelector('[data-use-calculated-totals]')?.addEventListener('click', () => calculate(true));
    calculate(!form.querySelector('[data-document-total]').value);
});
</script>
@endpush
