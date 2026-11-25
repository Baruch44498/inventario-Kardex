<section class="panel supplier-invoice-payments" id="pagos-factura">
    @php
        $pagado = $factura->montoPagado();
        $avancePago = (float) $factura->total > 0 ? min(100, max(0, round($pagado / (float) $factura->total * 100))) : 0;
        $saldoCentavos = round(round((float) $factura->total, 2) - $pagado, 4);
        $diferenciaCentavos = round($saldoCentavos - $saldoFactura, 4);
        $cierreEnCentavos = $saldoCentavos > 0
            && abs($diferenciaCentavos) > 0.00005
            && abs($diferenciaCentavos) <= 0.005
            && ((int) round($saldoCentavos * 10000)) % 100 === 0;
        $saldoParaPago = $cierreEnCentavos ? $saldoCentavos : $saldoFactura;
        $maximoPago = max($saldoFactura, $saldoParaPago);
    @endphp
    <div class="panel-heading panel-heading--split"><h2>Pagos de la factura</h2>
        @if (auth()->user()->puede('contabilidad.ver'))
            <a href="{{ route('cuentas-pagar.index') }}" class="table-primary-link">Volver a Cuentas por pagar</a>
        @endif
    </div>
    <div class="supplier-invoice-payments__metrics">
        <article class="metric-card"><span>Importe facturado</span><strong><x-ui.money :value="$factura->total" :currency="$factura->moneda" /></strong></article>
        <article class="metric-card"><span>Pagado</span><strong><x-ui.money :value="$pagado" :currency="$factura->moneda" /></strong></article>
        <article class="metric-card supplier-invoice-payments__balance"><span>Saldo pendiente</span><strong><x-ui.money :value="$saldoFactura" :currency="$factura->moneda" /></strong></article>
    </div>
    <div class="supplier-invoice-payments__progress"><div><span>Progreso de pago</span><strong>{{ $avancePago }}%</strong></div><progress value="{{ $avancePago }}" max="100" aria-label="{{ $avancePago }}% del total pagado"></progress></div>
    <div @class(['supplier-invoice-payments__columns', 'supplier-invoice-payments__columns--with-form' => $puedeRegistrarPago && $saldoFactura > 0 && ! $factura->estaAnulada(), 'supplier-invoice-payments__columns--empty' => $factura->pagos->isEmpty() && $puedeRegistrarPago && $saldoFactura > 0 && ! $factura->estaAnulada()])>
        <section class="supplier-invoice-payments__history" aria-label="Historial de pagos">
            <div class="panel-heading"><h3>Historial de pagos</h3><span>{{ $factura->pagos->count() }} movimiento{{ $factura->pagos->count() === 1 ? '' : 's' }}</span></div>
            @if ($factura->pagos->isNotEmpty())
                <div class="table-wrap table-wrap--responsive" data-responsive-table>
                    <table class="data-table data-table--responsive supplier-invoice-payments__table">
                        <thead><tr><th>Fecha</th><th class="text-right">Monto</th><th>Medio y referencia</th><th>Registro</th><th>Estado / acción</th></tr></thead>
                        <tbody>
                            @foreach ($factura->pagos as $pago)
                                <tr @class(['supplier-invoice-payments__row', 'supplier-invoice-payments__row--cancelled' => $pago->estaAnulado()])>
                                    <td data-label="Fecha">{{ $pago->fecha_pago?->format('d/m/Y') }}</td>
                                    <td class="text-right" data-label="Monto"><x-ui.money :value="$pago->monto" :currency="$factura->moneda" /></td>
                                    <td data-label="Medio y referencia">{{ ucfirst(strtolower($pago->medio_pago)) }} @if ($pago->referencia) · {{ $pago->referencia }} @endif</td>
                                    <td data-label="Registro">{{ $pago->registrador?->nombreVisible() }}</td>
                                    <td data-label="Estado / acción">
                                        @if ($pago->estaAnulado())
                                            <x-ui.status-badge tone="danger">Anulado</x-ui.status-badge>
                                            <small class="supplier-invoice-payments__reason">{{ $pago->motivo_anulacion }} · {{ $pago->anulador?->nombreVisible() }}</small>
                                        @elseif ($puedeRegistrarPago)
                                            <x-ui.status-badge tone="success">Vigente</x-ui.status-badge>
                                            <details class="supplier-invoice-payments__cancel"><summary>Anular este pago</summary>
                                                <form method="POST" action="{{ route('facturas-proveedor.pagos.anular', [$factura, $pago]) }}" data-confirm="¿Anular este pago y devolver el importe al saldo?">
                                                    @csrf @method('PATCH')
                                                    <label class="form-field"><span>Motivo de anulación</span><input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required></label>
                                                    <button type="submit" class="button button--danger button--small">Anular pago</button>
                                                </form>
                                            </details>
                                        @else
                                            <x-ui.status-badge tone="success">Vigente</x-ui.status-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="supplier-invoice-payments__subtotal"><span>Total de pagos vigentes</span><strong><x-ui.money :value="$pagado" :currency="$factura->moneda" /></strong></div>
            @else
                <div class="empty-table-state supplier-invoice-payments__empty"><strong>Aún no hay pagos registrados</strong><span>Los movimientos aparecerán aquí al registrar el primer pago.</span></div>
            @endif
        </section>
        @if ($puedeRegistrarPago && $saldoFactura > 0 && ! $factura->estaAnulada())
            <section class="supplier-invoice-payments__new" aria-label="Nuevo pago">
                <h3>Nuevo pago</h3>
                @if ($errors->hasAny(['fecha_pago', 'monto', 'medio_pago', 'referencia', 'observacion']))
                    <div class="notice notice--danger notice--block" role="alert"><div><strong>Revisa el pago</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
                @endif
                <form method="POST" action="{{ route('facturas-proveedor.pagos.store', $factura) }}" class="supplier-invoice-payment-form" data-supplier-invoice-payment-form data-currency="{{ $factura->moneda }}">
                    @csrf
                    <label class="form-field"><span>Fecha del pago</span><input type="date" id="fecha-pago" name="fecha_pago" value="{{ old('fecha_pago', today()->toDateString()) }}" min="{{ $factura->fecha_emision?->toDateString() }}" max="{{ today()->toDateString() }}" required @if ($errors->has('fecha_pago')) aria-invalid="true" aria-describedby="fecha-pago-error" @endif>@error('fecha_pago')<small id="fecha-pago-error" class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="form-field"><span>Monto ({{ $factura->moneda }})</span><span class="supplier-invoice-payment-form__amount"><span>{{ $factura->simboloMoneda() }}</span><input type="number" id="monto-pago" name="monto" data-supplier-invoice-payment-amount data-supplier-invoice-payment-balance="{{ $saldoFactura }}" data-supplier-invoice-payment-full="{{ $saldoParaPago }}" value="{{ old('monto') }}" min="0.0001" max="{{ $maximoPago }}" step="0.0001" required @if ($errors->has('monto') || $cierreEnCentavos) aria-describedby="@if($errors->has('monto')) monto-pago-error @endif @if($cierreEnCentavos) monto-pago-redondeo @endif" @endif @if ($errors->has('monto')) aria-invalid="true" @endif></span>@error('monto')<small id="monto-pago-error" class="field-error">{{ $message }}</small>@enderror @if ($cierreEnCentavos)<small id="monto-pago-redondeo">Al pagar {{ $factura->simboloMoneda() }} {{ number_format($saldoParaPago, 2, '.', ',') }}, se aplicará un ajuste de redondeo de {{ $diferenciaCentavos > 0 ? '+' : '' }}{{ number_format($diferenciaCentavos * 100, 2, '.', ',') }} céntimos al total de la factura.</small>@endif</label>
                    <button type="button" class="button button--ghost button--small" data-supplier-invoice-payment-fill hidden>Pagar saldo completo</button>
                    <label class="form-field"><span>Medio</span><select id="medio-pago" name="medio_pago" required @if ($errors->has('medio_pago')) aria-invalid="true" aria-describedby="medio-pago-error" @endif>
                        @foreach (['TRANSFERENCIA' => 'Transferencia', 'EFECTIVO' => 'Efectivo', 'CHEQUE' => 'Cheque', 'TARJETA' => 'Tarjeta', 'OTRO' => 'Otro'] as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(old('medio_pago') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>@error('medio_pago')<small id="medio-pago-error" class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="form-field"><span>Referencia</span><input type="text" id="referencia-pago" name="referencia" value="{{ old('referencia') }}" maxlength="100" placeholder="N.º de operación" @if ($errors->has('referencia')) aria-invalid="true" aria-describedby="referencia-pago-error" @endif>@error('referencia')<small id="referencia-pago-error" class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="form-field"><span>Observación (opcional)</span><input type="text" id="observacion-pago" name="observacion" value="{{ old('observacion') }}" maxlength="500" @if ($errors->has('observacion')) aria-invalid="true" aria-describedby="observacion-pago-error" @endif>@error('observacion')<small id="observacion-pago-error" class="field-error">{{ $message }}</small>@enderror</label>
                    <output class="supplier-invoice-payment-form__preview" data-supplier-invoice-payment-preview aria-live="polite">Saldo después de este pago: <x-ui.money :value="$saldoFactura" :currency="$factura->moneda" /></output>
                    <button type="submit" class="button button--primary">Registrar pago</button>
                </form>
            </section>
        @endif
    </div>
</section>
