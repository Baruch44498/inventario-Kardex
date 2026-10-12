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
            <p>Recepción primero, factura después. Se concilian las cantidades recibidas con la OC aprobada.</p>
        </div>
    </section>

    @if ($errors->any())
        <div class="notice notice--danger notice--block" role="alert"><x-ui.icon name="error" :size="18" /><div><strong>Revisa la factura antes de guardarla.</strong><span>{{ $errors->first() }}</span></div></div>
    @endif

    @php
        $facturacionParcialActiva = collect($filas)->contains(function (array $fila, int $indice): bool {
            $cantidadAnterior = old("detalles.{$indice}.cantidad");

            return $cantidadAnterior !== null
                && abs((float) $cantidadAnterior - (float) $fila['pendiente']) >= 0.0005;
        });
        $plazoPago = trim((string) $orden->condiciones_pago);
        $diasCredito = preg_match('/^(?:cr[eé]dito(?:\s+a)?\s*)?(\d{1,3})\s*d[ií]as?$/iu', $plazoPago, $coincidencias)
            ? (int) $coincidencias[1]
            : null;
        $fechaEmision = old('fecha_emision', now()->toDateString());
        $fechaEmisionValida = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fechaEmision)
            && checkdate((int) substr($fechaEmision, 5, 2), (int) substr($fechaEmision, 8, 2), (int) substr($fechaEmision, 0, 4));
        $vencimientoSugerido = $diasCredito !== null && $fechaEmisionValida
            ? \Illuminate\Support\Carbon::parse($fechaEmision)->addDays($diasCredito)->toDateString()
            : null;
    @endphp

    <section class="order-context-card supplier-invoice-order-context">
        <div class="order-context-card__main"><span class="order-context-card__icon"><x-ui.icon name="purchase-order" :size="25" /></span><div><span>Orden vinculada</span><strong>{{ $orden->codigo }}</strong><small>{{ $orden->proveedor?->nombreVisible() }} · RUC {{ $orden->proveedor?->ruc }}</small></div></div>
        <dl class="order-context-card__facts"><div><dt>Moneda</dt><dd>{{ $orden->moneda }}</dd></div><div><dt>Total autorizado</dt><dd><x-ui.money :value="$orden->total" :currency="$orden->moneda" /></dd></div><div><dt>Estado</dt><dd>{{ $orden->estadoVisible() }}</dd></div></dl>
    </section>

    <form method="POST" action="{{ route('facturas-proveedor.store') }}" enctype="multipart/form-data" class="supplier-invoice-form" data-supplier-invoice-form data-dirty-form data-loading-form>
        @csrf
        <input type="hidden" name="orden_compra_id" value="{{ $orden->id }}">

        <section class="panel form-panel supplier-invoice-create-panel">
            <div class="panel-heading"><p class="eyebrow">Identificación fiscal</p><h2>Datos del documento</h2><p>Completa únicamente los datos que identifican el comprobante recibido.</p></div>
            <div class="form-grid form-grid--three">
                <label class="form-field"><span>Tipo de documento *</span><select name="tipo_documento" required><option value="FACTURA" @selected(old('tipo_documento', 'FACTURA') === 'FACTURA')>Factura</option><option value="BOLETA" @selected(old('tipo_documento') === 'BOLETA')>Boleta</option></select></label>
                <label class="form-field"><span>Serie *</span><input type="text" name="serie" value="{{ old('serie') }}" maxlength="20" placeholder="F001" required></label>
                <label class="form-field"><span>Número *</span><input type="text" name="numero" value="{{ old('numero') }}" maxlength="30" placeholder="00001234" required></label>
                <label class="form-field"><span>Fecha de emisión *</span><input type="date" name="fecha_emision" value="{{ old('fecha_emision', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label>
                <label class="form-field"><span>Fecha de vencimiento</span><input type="date" name="fecha_vencimiento" value="{{ old('fecha_vencimiento', $vencimientoSugerido) }}"><small>Opcional. Confirma la fecha indicada en el comprobante.</small></label>
                <div class="form-field"><span>Moneda y tipo de cambio</span><div class="readonly-field"><strong>{{ $orden->moneda }}@if($orden->moneda === 'USD') · TC {{ number_format((float) $orden->tipo_cambio, 2) }}@endif</strong><small>Protegidos por la OC aprobada</small></div></div>
                <div class="form-field form-field--span-2 supplier-invoice-upload">
                    <label for="supplier-invoice-file">Documento original *</label>
                    <input id="supplier-invoice-file" type="file" name="archivo_original" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="supplier-invoice-file-help supplier-invoice-file-error" required>
                    <div class="supplier-invoice-upload__controls" hidden>
                        <label for="supplier-invoice-file" class="button button--ghost supplier-invoice-upload__choose">Elegir archivo</label>
                        <span id="supplier-invoice-file-name" role="status" aria-live="polite">Ningún archivo seleccionado</span>
                        <button type="button" class="button button--ghost" id="supplier-invoice-file-remove" hidden>Quitar</button>
                    </div>
                    <small id="supplier-invoice-file-help">PDF digital o imagen legible. Máximo 15 MB.</small>
                    <small id="supplier-invoice-file-error" class="field-error" role="status" hidden></small>
                </div>
                <label class="form-field form-field--span-3"><span>Observación</span><textarea name="observacion" rows="2" maxlength="500" placeholder="Referencia o información adicional">{{ old('observacion') }}</textarea></label>
            </div>
        </section>

        <section class="panel supplier-invoice-lines-panel supplier-invoice-create-panel">
            <div class="panel-heading">
                <p class="eyebrow">Conciliación protegida</p>
                <h2>Productos recibidos pendientes de facturar</h2>
                <p>Por defecto se factura todo el saldo recibido. Activa el modo parcial únicamente si el comprobante cubre una cantidad menor. La diferencia de costo se aplicará solo a lo que siga en stock; el resto quedará como diferencia contable para Contabilidad.</p>
            </div>
            <label class="supplier-invoice-partial-control">
                <input type="checkbox" data-partial-billing-toggle @checked($facturacionParcialActiva)>
                <span class="supplier-invoice-partial-control__switch" aria-hidden="true"></span>
                <span>
                    <strong>Facturación parcial</strong>
                    <small>Permitir cambiar las cantidades facturadas sin modificar precios, moneda ni IGV.</small>
                </span>
            </label>
            <div class="notice notice--info notice--block"><x-ui.icon name="lock" :size="18" /><div><strong>Importes no editables</strong><span>Si el comprobante físico difiere de la OC, no alteres la línea: revisa primero la cotización u orden con Logística.</span></div></div>
            @error('detalles')<div class="notice notice--danger notice--block">{{ $message }}</div>@enderror
            <p class="supplier-invoice-line-summary" id="supplier-invoice-line-summary" role="status" aria-live="polite">{{ $filas->count() }} productos · se factura todo el saldo recibido · Total {{ $orden->moneda === 'USD' ? '$' : 'S/' }} —</p>
            <div class="table-wrap table-wrap--wide">
                <table class="data-table supplier-invoice-lines-table">
                    <thead><tr><th>Producto</th><th>Recepción</th><th class="text-right">Saldo recibido</th><th>Cantidad facturada <small>Saldo completo por defecto</small></th><th class="text-right">Costo autorizado</th><th>IGV</th><th class="text-right">Importes calculados</th></tr></thead>
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
                                <td data-label="Producto"><input type="hidden" name="detalles[{{ $indice }}][orden_compra_detalle_id]" value="{{ $detalle->id }}"><input type="hidden" name="detalles[{{ $indice }}][nota_ingreso_detalle_id]" value="{{ $ingresoDetalle->id }}"><strong>{{ $detalle->producto?->codigo }}</strong><span>{{ $detalle->producto?->descripcion }}</span><small>{{ $detalle->producto?->unidadMedida?->codigo ?? 'UND' }}</small></td>
                                <td data-label="Recepción"><strong>{{ $nota->codigo }}</strong><span>{{ $nota->fecha_ingreso?->format('d/m/Y') }}</span>@error("detalles.{$indice}.nota_ingreso_detalle_id")<small class="field-error">{{ $message }}</small>@enderror</td>
                                <td class="text-right" data-label="Saldo recibido"><strong><x-ui.quantity :value="$fila['pendiente']" /></strong></td>
                                <td data-label="Cantidad facturada"><input class="table-input" type="number" name="detalles[{{ $indice }}][cantidad]" value="{{ $cantidad }}" min="0" max="{{ $fila['pendiente'] }}" step="0.001" data-invoice-quantity data-full-quantity="{{ $fila['pendiente'] }}" aria-label="Cantidad facturada de {{ $detalle->producto?->codigo }}" @readonly(! $facturacionParcialActiva)><small data-invoice-quantity-help hidden>{{ $facturacionParcialActiva ? 'Máximo recibido' : 'Saldo completo' }}</small>@error("detalles.{$indice}.cantidad")<small class="field-error">{{ $message }}</small>@enderror</td>
                                <td class="text-right" data-label="Costo autorizado"><strong><x-ui.money :value="$fila['costo_total_default']" :currency="$orden->moneda" /></strong><small>Según OC</small></td>
                                <td data-label="IGV"><span class="badge badge--{{ $afecto ? 'info' : 'neutral' }}">{{ $afecto ? '18% incluido' : 'No aplica' }}</span></td>
                                <td class="text-right supplier-invoice-line-amounts" data-label="Importes calculados"><span>Base <b data-invoice-base>—</b></span><span>IGV <b data-invoice-igv>—</b></span><strong>Total <b data-invoice-total>—</b></strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel supplier-invoice-totals-panel supplier-invoice-create-panel">
            <div><p class="eyebrow">Totales calculados</p><h2>Resultado de la conciliación</h2><p>Estos importes se recalculan desde la OC según las cantidades facturadas y no se envían como campos editables.</p></div>
            <dl class="order-context-card__facts">
                <div><dt>Base imponible</dt><dd><span data-document-base>0.00</span> {{ $orden->moneda }}</dd></div>
                <div><dt>IGV / crédito fiscal</dt><dd><span data-document-igv>0.00</span> {{ $orden->moneda }}</dd></div>
                <div><dt>Total conciliado</dt><dd><strong><span data-document-total>0.00</span> {{ $orden->moneda }}</strong></dd></div>
            </dl>
            <div class="supplier-invoice-physical-check">
                <label for="supplier-invoice-physical-total">Total del comprobante físico (opcional)</label>
                <div class="supplier-invoice-physical-check__field">
                    <span id="supplier-invoice-currency">{{ $orden->moneda === 'USD' ? '$' : 'S/' }}</span>
                    <input id="supplier-invoice-physical-total" type="text" inputmode="decimal" autocomplete="off" aria-describedby="supplier-invoice-physical-result">
                </div>
                <small id="supplier-invoice-physical-result" role="status">Escribe el total del documento para compararlo con el total conciliado. No se guarda.</small>
            </div>
        </section>

        <div class="form-actions form-actions--sticky"><button type="button" class="button button--ghost" data-cancel-form data-cancel-url="{{ route('ordenes-compra.show', $orden) }}">Cancelar</button><button type="submit" class="button button--primary" data-submit-button data-loading-text="Registrando factura..."><x-ui.icon name="invoice" :size="17" /><span data-submit-label>Registrar factura</span></button></div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/supplier-invoice-form.js') }}" defer></script>
@endpush
