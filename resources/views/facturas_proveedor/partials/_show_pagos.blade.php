<section class="panel" id="pagos-factura">
    <div class="panel-heading"><p class="eyebrow">Cuentas por pagar</p><h2>Pagos de la factura</h2></div>
    @if (auth()->user()->puede('contabilidad.ver'))
        <a href="{{ route('cuentas-pagar.index') }}" class="back-link">Volver a Cuentas por pagar</a>
    @endif
    <dl class="supplier-info-grid">
        <div><dt>Importe facturado</dt><dd><x-ui.money :value="$factura->total" :currency="$factura->moneda" /></dd></div>
        <div><dt>Pagado</dt><dd><x-ui.money :value="$factura->montoPagado()" :currency="$factura->moneda" /></dd></div>
        <div><dt>Saldo pendiente</dt><dd><strong><x-ui.money :value="$factura->saldoPendiente()" :currency="$factura->moneda" /></strong></dd></div>
    </dl>

    @if ($puedeRegistrarPago && $factura->saldoPendiente() > 0 && ! $factura->estaAnulada())
        <form method="POST" action="{{ route('facturas-proveedor.pagos.store', $factura) }}" class="supplier-invoice-filter">
            @csrf
            <label class="form-field"><span>Fecha del pago</span><input type="date" name="fecha_pago" value="{{ old('fecha_pago', today()->toDateString()) }}" min="{{ $factura->fecha_emision?->toDateString() }}" max="{{ today()->toDateString() }}" required></label>
            <label class="form-field"><span>Monto ({{ $factura->moneda }})</span><input type="number" name="monto" value="{{ old('monto') }}" min="0.0001" max="{{ $factura->saldoPendiente() }}" step="0.0001" required></label>
            <label class="form-field"><span>Medio</span><select name="medio_pago" required>
                @foreach (['TRANSFERENCIA' => 'Transferencia', 'EFECTIVO' => 'Efectivo', 'CHEQUE' => 'Cheque', 'TARJETA' => 'Tarjeta', 'OTRO' => 'Otro'] as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(old('medio_pago') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select></label>
            <label class="form-field"><span>Referencia</span><input type="text" name="referencia" value="{{ old('referencia') }}" maxlength="100"></label>
            <label class="form-field"><span>Observación</span><input type="text" name="observacion" value="{{ old('observacion') }}" maxlength="500"></label>
            <div class="filter-actions"><button type="submit" class="button button--primary">Registrar pago</button></div>
        </form>
        @if ($errors->any())
            <div class="notice notice--danger notice--block" role="alert"><div><strong>Revisa el pago</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
        @endif
    @endif

    @if ($factura->pagos->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Fecha</th><th class="text-right">Monto</th><th>Medio y referencia</th><th>Registro</th><th>Estado / acción</th></tr></thead>
                <tbody>
                    @foreach ($factura->pagos as $pago)
                        <tr>
                            <td>{{ $pago->fecha_pago?->format('d/m/Y') }}</td>
                            <td class="text-right"><x-ui.money :value="$pago->monto" :currency="$factura->moneda" /></td>
                            <td>{{ ucfirst(strtolower($pago->medio_pago)) }} @if ($pago->referencia) · {{ $pago->referencia }} @endif</td>
                            <td>{{ $pago->registrador?->nombreVisible() }}</td>
                            <td>
                                @if ($pago->estaAnulado())
                                    <span class="badge badge--danger">Anulado</span><br>{{ $pago->motivo_anulacion }} · {{ $pago->anulador?->nombreVisible() }}
                                @elseif ($puedeRegistrarPago)
                                    <form method="POST" action="{{ route('facturas-proveedor.pagos.anular', [$factura, $pago]) }}" data-confirm="¿Anular este pago y devolver el importe al saldo?">
                                        @csrf @method('PATCH')
                                        <label class="form-field"><span>Motivo de anulación</span><input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required></label>
                                        <button type="submit" class="button button--danger button--small">Anular pago</button>
                                    </form>
                                @else
                                    <span class="badge badge--success">Vigente</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p>Aún no se registran pagos.</p>
    @endif
</section>
