@extends('layouts.app')

@section('title', 'Cobros · '.$cotizacion->codigo)
@section('page-kicker', 'Contabilidad')
@section('page-title', 'Cobros de '.$cotizacion->codigo)

@section('content')
    <a href="{{ route('cuentas-cobrar.index') }}" class="back-link">Volver a Cuentas por cobrar</a>

    <section class="module-header">
        <div><p class="eyebrow">{{ $cotizacion->proforma_id ? 'Venta directa' : 'Trabajo finalizado' }}</p><h1>{{ $cotizacion->codigo }}</h1><p>{{ $cotizacion->cliente_nombre }} · {{ $cotizacion->proforma_id ? $cotizacion->proforma?->codigo : $cotizacion->ordenOperacion?->codigo_orden }}</p></div>
    </section>

    <section class="panel">
        <div class="panel-heading"><p class="eyebrow">Moneda {{ $cotizacion->moneda }}</p><h2>Saldo del documento comercial</h2></div>
        <dl class="supplier-info-grid">
            <div><dt>Total cotizado</dt><dd><x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></dd></div>
            <div><dt>Cobrado</dt><dd><x-ui.money :value="$cotizacion->montoCobrado()" :currency="$cotizacion->moneda" /></dd></div>
            <div><dt>Saldo por cobrar</dt><dd><strong><x-ui.money :value="$cotizacion->saldoPorCobrar()" :currency="$cotizacion->moneda" /></strong></dd></div>
        </dl>
        <p>Este es un control interno de cobros vinculado a la cotización; no emite comprobantes tributarios.</p>
    </section>

    @if ($puedeRegistrarCobro && $habilitada && $cotizacion->saldoPorCobrar() > 0)
        @php($fechaInicio = $cotizacion->proforma_id ? $cotizacion->fecha_emision?->toDateString() : ($cotizacion->ordenOperacion?->cerrado_en?->toDateString() ?? $cotizacion->fecha_emision?->toDateString()))
        <section class="panel" id="nuevo-cobro">
            <div class="panel-heading"><p class="eyebrow">Registrar movimiento</p><h2>Nuevo cobro</h2></div>
            <form method="POST" action="{{ route('cuentas-cobrar.cobros.store', $cotizacion) }}" class="supplier-invoice-filter">
                @csrf
                <label class="form-field"><span>Fecha de cobro</span><input type="date" name="fecha_cobro" value="{{ old('fecha_cobro', today()->toDateString()) }}" min="{{ $fechaInicio }}" max="{{ today()->toDateString() }}" required></label>
                <label class="form-field"><span>Monto ({{ $cotizacion->moneda }})</span><input type="number" name="monto" value="{{ old('monto') }}" min="0.0001" max="{{ $cotizacion->saldoPorCobrar() }}" step="0.0001" required></label>
                <label class="form-field"><span>Medio de cobro</span><select name="medio_cobro" required>
                    @foreach (['TRANSFERENCIA' => 'Transferencia', 'EFECTIVO' => 'Efectivo', 'CHEQUE' => 'Cheque', 'TARJETA' => 'Tarjeta', 'OTRO' => 'Otro'] as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('medio_cobro') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select></label>
                <label class="form-field"><span>Referencia</span><input type="text" name="referencia" value="{{ old('referencia') }}" maxlength="100"></label>
                <label class="form-field"><span>Observación</span><input type="text" name="observacion" value="{{ old('observacion') }}" maxlength="500"></label>
                <div class="filter-actions"><button type="submit" class="button button--primary">Registrar cobro</button></div>
            </form>
        </section>
    @endif

    @if ($errors->any())
        <div class="notice notice--danger notice--block" role="alert"><div><strong>Revisa el movimiento</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    <section class="panel">
        <div class="panel-heading"><p class="eyebrow">Trazabilidad</p><h2>Historial de cobros</h2></div>
        @if ($cotizacion->cobros->isNotEmpty())
            <div class="table-wrap"><table class="data-table">
                <thead><tr><th>Fecha</th><th class="text-right">Monto</th><th>Medio / referencia</th><th>Registrado por</th><th>Estado / acción</th></tr></thead>
                <tbody>@foreach ($cotizacion->cobros as $cobro)
                    <tr>
                        <td>{{ $cobro->fecha_cobro?->format('d/m/Y') }}</td>
                        <td class="text-right"><x-ui.money :value="$cobro->monto" :currency="$cotizacion->moneda" /></td>
                        <td>{{ ucfirst(strtolower($cobro->medio_cobro)) }} @if ($cobro->referencia) · {{ $cobro->referencia }} @endif</td>
                        <td>{{ $cobro->registrador?->nombreVisible() }}</td>
                        <td>
                            @if ($cobro->estaAnulado())
                                <span class="badge badge--danger">Anulado</span><br>{{ $cobro->motivo_anulacion }} · {{ $cobro->anulador?->nombreVisible() }}
                            @elseif ($puedeRegistrarCobro)
                                <form method="POST" action="{{ route('cuentas-cobrar.cobros.anular', [$cotizacion, $cobro]) }}" data-confirm="¿Anular este cobro y devolver el importe al saldo?">
                                    @csrf @method('PATCH')
                                    <label class="form-field"><span>Motivo de anulación</span><input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required></label>
                                    <button type="submit" class="button button--danger button--small">Anular cobro</button>
                                </form>
                            @else
                                <span class="badge badge--success">Vigente</span>
                            @endif
                        </td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @else
            <p>Aún no se registran cobros.</p>
        @endif
    </section>
@endsection
