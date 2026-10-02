@extends('layouts.app')

@section('title', 'Movimientos de tesorería')
@section('page-kicker', 'Contabilidad')
@section('page-title', 'Movimientos de tesorería')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Cobros y pagos registrados</p>
            <h1>Movimientos de tesorería</h1>
            <p>Importes vigentes por fecha de operación. El neto es cobros menos pagos; no representa el saldo bancario.</p>
        </div>
        <a href="{{ route('tesoreria.movimientos.csv', request()->only(['desde', 'hasta', 'tipo', 'moneda'])) }}" class="button button--ghost" data-file-download>Descargar CSV de movimientos</a>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('tesoreria.movimientos.index') }}" class="supplier-invoice-filter">
            <label class="form-field"><span>Desde</span><input type="date" name="desde" value="{{ request('desde') }}"></label>
            <label class="form-field"><span>Hasta</span><input type="date" name="hasta" value="{{ request('hasta') }}"></label>
            <label class="form-field"><span>Tipo</span><select name="tipo"><option value="">Ambos</option><option value="COBRO" @selected(request('tipo') === 'COBRO')>Cobros</option><option value="PAGO" @selected(request('tipo') === 'PAGO')>Pagos</option></select></label>
            <label class="form-field"><span>Moneda</span><select name="moneda"><option value="">Ambas</option><option value="PEN" @selected(request('moneda') === 'PEN')>PEN</option><option value="USD" @selected(request('moneda') === 'USD')>USD</option></select></label>
            <div class="filter-actions"><button type="submit" class="button button--primary">Filtrar</button><a href="{{ route('tesoreria.movimientos.index') }}" class="button button--ghost">Limpiar</a></div>
        </form>
        @error('desde') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        @error('hasta') <p class="field-error" role="alert">{{ $message }}</p> @enderror
    </section>

    <section class="panel" aria-label="Resumen de movimientos filtrados">
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Moneda</th><th class="text-right">Cobros</th><th class="text-right">Pagos</th><th class="text-right">Neto</th></tr></thead>
                <tbody>
                    @foreach (['PEN', 'USD'] as $moneda)
                        @php($cobrado = (float) ($resumen->get($moneda)?->firstWhere('tipo', 'COBRO')?->total ?? 0))
                        @php($pagado = (float) ($resumen->get($moneda)?->firstWhere('tipo', 'PAGO')?->total ?? 0))
                        <tr>
                            <th scope="row">{{ $moneda }}</th>
                            <td class="text-right"><x-ui.money :value="$cobrado" :currency="$moneda" /></td>
                            <td class="text-right"><x-ui.money :value="$pagado" :currency="$moneda" /></td>
                            <td class="text-right"><strong><x-ui.money :value="$cobrado - $pagado" :currency="$moneda" /></strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" aria-label="Detalle de movimientos">
        @if ($registros->isNotEmpty())
            <div class="table-wrap table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--responsive supplier-invoice-list-table">
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>Documento</th><th>Tercero</th><th>Medio</th><th>Referencia</th><th class="text-right">Importe</th></tr></thead>
                    <tbody>
                        @foreach ($registros as $registro)
                            <tr>
                                <td data-label="Fecha">{{ \Illuminate\Support\Carbon::parse($registro->fecha)->format('d/m/Y') }}</td>
                                <td data-label="Tipo"><span class="badge badge--{{ $registro->tipo === 'COBRO' ? 'success' : 'info' }}">{{ $registro->tipo === 'COBRO' ? 'Cobro' : 'Pago' }}</span></td>
                                <td data-label="Documento">
                                    @if ($registro->tipo === 'COBRO')
                                        <a class="table-primary-link" href="{{ route('cuentas-cobrar.show', $registro->documento_id) }}">{{ $registro->documento }}</a>
                                    @else
                                        <a class="table-primary-link" href="{{ route('facturas-proveedor.show', $registro->documento_id) }}">{{ $registro->serie }}-{{ $registro->documento }}</a>
                                    @endif
                                </td>
                                <td data-label="Tercero">{{ $registro->tercero }}</td>
                                <td data-label="Medio">{{ str_replace('_', ' ', $registro->medio) }}</td>
                                <td data-label="Referencia">{{ $registro->referencia ?: '—' }}</td>
                                <td class="text-right" data-label="Importe"><x-ui.money :value="$registro->monto" :currency="$registro->moneda" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$registros" />
        @else
            <div class="empty-table-state"><strong>No hay movimientos vigentes con estos filtros</strong><span>Los cobros y pagos aparecerán aquí al registrarlos.</span></div>
        @endif
    </section>
@endsection
