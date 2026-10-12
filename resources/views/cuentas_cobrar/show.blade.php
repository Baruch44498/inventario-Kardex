@extends('layouts.app')

@section('title', 'Cobros · '.$cotizacion->codigo)
@section('page-kicker', 'Contabilidad')
@section('page-title', 'Cobros de '.$cotizacion->codigo)

@section('content')
    @php
        $cobrado = $cotizacion->montoCobrado();
        $saldo = $cotizacion->saldoPorCobrar();
        $avance = (float) $cotizacion->total > 0
            ? min(100, max(0, round($cobrado / (float) $cotizacion->total * 100)))
            : 0;
        $puedeVerComercial = auth()->user()->puede('proformas.ver');
        $puedeVerOrdenes = auth()->user()->puede('ordenes.ver');
    @endphp

    <div class="receivables-page receivables-detail">
        <a href="{{ route('cuentas-cobrar.index') }}" class="back-link receivables-detail__back">
            <x-ui.icon name="arrow-left" :size="17" /> Volver a Cuentas por cobrar
        </a>

        <section class="ui-page-header receivables-detail__header">
            <div class="ui-page-header__content">
                <p class="eyebrow">{{ $cotizacion->proforma_id ? 'Venta directa' : 'Trabajo finalizado' }}</p>
                <div class="ui-page-header__title-row">
                    <h1>
                        @if ($puedeVerComercial)
                            <a class="table-primary-link" href="{{ route('cotizaciones-cliente.show', $cotizacion) }}">{{ $cotizacion->codigo }}</a>
                        @else
                            {{ $cotizacion->codigo }}
                        @endif
                    </h1>
                    <x-ui.status-badge :tone="$saldo === 0.0 ? 'success' : ($cobrado > 0 ? 'warning' : 'info')">
                        {{ $saldo === 0.0 ? 'Cobrado' : ($cobrado > 0 ? 'Cobro parcial' : 'Pendiente') }}
                    </x-ui.status-badge>
                </div>
                <p class="ui-page-header__description receivables-detail__subtitle">
                    @if ($cotizacion->proforma_id)
                        @if ($puedeVerComercial && $cotizacion->proforma)
                            <a class="table-primary-link" href="{{ route('proformas.show', $cotizacion->proforma) }}">{{ $cotizacion->proforma->codigo }}</a>
                        @else
                            {{ $cotizacion->proforma?->codigo ?: 'Proforma no disponible' }}
                        @endif
                    @elseif ($puedeVerOrdenes && $cotizacion->ordenOperacion)
                        <a class="table-primary-link" href="{{ route('ordenes-operacion.show', $cotizacion->ordenOperacion) }}">{{ $cotizacion->ordenOperacion->codigo_orden }}</a>
                    @else
                        {{ $cotizacion->ordenOperacion?->codigo_orden ?: 'Orden no disponible' }}
                    @endif
                </p>
            </div>
        </section>

        <section class="panel receivables-panel receivables-detail__overview">
            <header class="receivables-detail__section-heading">
                <h2>Saldo del documento comercial</h2>
                <span>Moneda {{ $cotizacion->moneda }}</span>
            </header>
            <div class="receivables-detail__totals">
                <article class="metric-card metric-card--summary">
                    <span>Total cotizado</span>
                    <strong><x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></strong>
                </article>
                <article class="metric-card metric-card--summary">
                    <span>Cobrado</span>
                    <strong><x-ui.money :value="$cobrado" :currency="$cotizacion->moneda" /></strong>
                </article>
                <article class="metric-card metric-card--summary metric-card--highlight">
                    <span>Saldo por cobrar</span>
                    <strong><x-ui.money :value="$saldo" :currency="$cotizacion->moneda" /></strong>
                </article>
            </div>
            <div class="receivables-detail__progress">
                <div><span>Progreso de cobro</span><strong>{{ $avance }}%</strong></div>
                <progress class="progress-bar progress-bar--large progress-bar--success" value="{{ $avance }}" max="100" aria-label="{{ $avance }}% del total cobrado"></progress>
            </div>
            <dl class="receivables-detail__facts">
                <div><dt>Emisión</dt><dd>{{ $cotizacion->fecha_emision?->format('d/m/Y') ?: 'No indicada' }}</dd></div>
                <div>
                    <dt>Cliente</dt>
                    <dd>{{ $cotizacion->cliente_nombre }} <small>{{ $cotizacion->cliente_documento ?: 'No indicado' }}</small></dd>
                </div>
                <div><dt>Condiciones de pago</dt><dd>{{ $cotizacion->condiciones_pago ?: 'No especificadas' }}</dd></div>
            </dl>
            <p class="receivables-detail__note">Este es un control interno de cobros vinculado a la cotización; no emite comprobantes tributarios.</p>
        </section>

        @if ($errors->any())
            <div class="notice notice--danger notice--block receivables-detail__errors" role="alert">
                <div><strong>Revisa el movimiento</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            </div>
        @endif

        <div @class([
            'receivables-detail__columns',
            'receivables-detail__columns--with-form' => $puedeRegistrarCobro && $habilitada && $cotizacion->saldoPorCobrar() > 0,
            'receivables-detail__columns--empty' => $cotizacion->cobros->isEmpty() && $puedeRegistrarCobro && $habilitada && $cotizacion->saldoPorCobrar() > 0,
        ])>
            <section class="panel receivables-panel receivables-history">
                <header class="receivables-detail__section-heading">
                    <h2>Historial de cobros</h2>
                    <span>{{ $cotizacion->cobros->count() }} movimiento{{ $cotizacion->cobros->count() === 1 ? '' : 's' }}</span>
                </header>
                @if ($cotizacion->cobros->isNotEmpty())
                    <div class="table-wrap table-wrap--responsive receivables-history__wrap" data-responsive-table>
                        <table class="data-table data-table--responsive receivables-history__table">
                            <thead>
                                <tr>
                                    <th scope="col">Fecha</th>
                                    <th scope="col" class="text-right">Monto</th>
                                    <th scope="col">Medio / referencia</th>
                                    <th scope="col">Registrado por</th>
                                    <th scope="col">Estado / acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cotizacion->cobros as $cobro)
                                    <tr @class(['receivables-history__row', 'receivables-history__row--cancelled' => $cobro->estaAnulado()])>
                                        <td data-label="Fecha">{{ $cobro->fecha_cobro?->format('d/m/Y') }}</td>
                                        <td data-label="Monto" class="text-right"><x-ui.money :value="$cobro->monto" :currency="$cotizacion->moneda" /></td>
                                        <td data-label="Medio / referencia">{{ ucfirst(strtolower($cobro->medio_cobro)) }} @if ($cobro->referencia) · {{ $cobro->referencia }} @endif</td>
                                        <td data-label="Registrado por">{{ $cobro->registrador?->nombreVisible() }}</td>
                                        <td data-label="Estado / acción">
                                            @if ($cobro->estaAnulado())
                                                <x-ui.status-badge tone="danger">Anulado</x-ui.status-badge>
                                                <small class="receivables-history__reason">{{ $cobro->motivo_anulacion }} · {{ $cobro->anulador?->nombreVisible() }}</small>
                                            @elseif ($puedeRegistrarCobro)
                                                <x-ui.status-badge tone="success">Vigente</x-ui.status-badge>
                                                <details class="receivables-history__cancel">
                                                    <summary>Anular este cobro</summary>
                                                    <form method="POST" action="{{ route('cuentas-cobrar.cobros.anular', [$cotizacion, $cobro]) }}" data-confirm="¿Anular este cobro y devolver el importe al saldo?">
                                                        @csrf @method('PATCH')
                                                        <label class="form-field"><span>Motivo de anulación</span><input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required></label>
                                                        <button type="submit" class="button button--danger button--small">Anular cobro</button>
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
                @else
                    <div class="empty-table-state receivables-history__empty">
                        <strong>Aún no se registran cobros.</strong>
                        <span>Los cobros registrados aparecerán aquí junto con su medio, referencia y responsable.</span>
                    </div>
                @endif
                @if ($cotizacion->cobros->isNotEmpty())
                    <div class="receivables-history__subtotal">
                        <span>Subtotal de cobros vigentes</span>
                        <strong><x-ui.money :value="$cobrado" :currency="$cotizacion->moneda" /></strong>
                    </div>
                @endif
            </section>

            @if ($puedeRegistrarCobro && $habilitada && $cotizacion->saldoPorCobrar() > 0)
                @php($fechaInicio = $cotizacion->proforma_id ? $cotizacion->fecha_emision?->toDateString() : ($cotizacion->ordenOperacion?->cerrado_en?->toDateString() ?? $cotizacion->fecha_emision?->toDateString()))
                <aside class="receivables-detail__entry">
                    <section class="panel receivables-panel receivables-entry-panel" id="nuevo-cobro">
                        <header class="receivables-detail__section-heading">
                            <h2>Nuevo cobro</h2>
                        </header>
                        <form method="POST" action="{{ route('cuentas-cobrar.cobros.store', $cotizacion) }}" class="receivables-entry-form" data-receivables-cobro-form data-currency="{{ $cotizacion->moneda }}">
                            @csrf
                            <label class="form-field">
                                <span>Fecha de cobro</span>
                                <input type="date" id="fecha-cobro" name="fecha_cobro" value="{{ old('fecha_cobro', today()->toDateString()) }}" min="{{ $fechaInicio }}" max="{{ today()->toDateString() }}" required @if ($errors->has('fecha_cobro')) aria-invalid="true" aria-describedby="fecha-cobro-error" @endif>
                                @error('fecha_cobro')<small id="fecha-cobro-error" class="field-error">{{ $message }}</small>@enderror
                            </label>
                            <div class="receivables-entry-form__amount">
                                <label class="form-field">
                                    <span>Monto ({{ $cotizacion->moneda }})</span>
                                    <span class="receivables-entry-form__money-input">
                                        <span aria-hidden="true">{{ $cotizacion->moneda === 'USD' ? 'US$' : 'S/' }}</span>
                                        <input type="number" id="monto-cobro" name="monto" value="{{ old('monto') }}" min="0.0001" max="{{ $cotizacion->saldoPorCobrar() }}" step="0.0001" required data-receivables-amount @if ($errors->has('monto')) aria-invalid="true" aria-describedby="monto-cobro-error" @endif>
                                    </span>
                                    @error('monto')<small id="monto-cobro-error" class="field-error">{{ $message }}</small>@enderror
                                </label>
                                <button type="button" class="button button--ghost button--small receivables-entry-form__fill" data-receivables-fill hidden>Cobrar saldo completo</button>
                            </div>
                            <output class="receivables-entry-form__preview" data-receivables-preview aria-live="polite">Saldo después de este cobro: <x-ui.money :value="$saldo" :currency="$cotizacion->moneda" /></output>
                            <label class="form-field">
                                <span>Medio de cobro</span>
                                <select id="medio-cobro" name="medio_cobro" required @if ($errors->has('medio_cobro')) aria-invalid="true" aria-describedby="medio-cobro-error" @endif>
                                    @foreach (['TRANSFERENCIA' => 'Transferencia', 'EFECTIVO' => 'Efectivo', 'CHEQUE' => 'Cheque', 'TARJETA' => 'Tarjeta', 'OTRO' => 'Otro'] as $valor => $etiqueta)
                                        <option value="{{ $valor }}" @selected(old('medio_cobro') === $valor)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                                @error('medio_cobro')<small id="medio-cobro-error" class="field-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="form-field">
                                <span>Referencia</span>
                                <input type="text" id="referencia-cobro" name="referencia" value="{{ old('referencia') }}" maxlength="100" placeholder="N.º de operación" @if ($errors->has('referencia')) aria-invalid="true" aria-describedby="referencia-cobro-error" @endif>
                                @error('referencia')<small id="referencia-cobro-error" class="field-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="form-field">
                                <span>Observación (opcional)</span>
                                <input type="text" id="observacion-cobro" name="observacion" value="{{ old('observacion') }}" maxlength="500" @if ($errors->has('observacion')) aria-invalid="true" aria-describedby="observacion-cobro-error" @endif>
                                @error('observacion')<small id="observacion-cobro-error" class="field-error">{{ $message }}</small>@enderror
                            </label>
                            <button type="submit" class="button button--primary receivables-entry-form__submit">Registrar cobro</button>
                        </form>
                    </section>
                </aside>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/cuentas-cobrar-show.js') }}" defer></script>
@endpush
