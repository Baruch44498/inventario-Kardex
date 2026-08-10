@extends('layouts.app')

@section('title', $cotizacion->codigo)
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Detalle de cotización')

@section('content')
    <a href="{{ route('cotizaciones-proveedor.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a cotizaciones
    </a>

    <section class="supplier-quote-hero">
        <div>
            <p class="eyebrow">Cotización de proveedor</p>
            <h1>{{ $cotizacion->codigo }}</h1>
            <p>
                {{ $cotizacion->proveedor->nombreVisible() }}
                · {{ $cotizacion->fecha_cotizacion->format('d/m/Y') }}
                · {{ $cotizacion->moneda }}
            </p>
        </div>

        <div class="supplier-quote-hero__actions">
            <span class="badge badge--{{ $cotizacion->estado === 'ANULADA' ? 'danger' : ($cotizacion->estado === 'SELECCIONADA' ? 'success' : 'info') }}">
                {{ $cotizacion->estado === 'ANULADA' ? 'INVALIDADA' : $cotizacion->estado }}
            </span>

            @if ($cotizacion->puedeEditar())
                <a href="{{ route('cotizaciones-proveedor.edit', $cotizacion->id) }}"
                    class="button button--ghost">
                    <x-ui.icon name="edit" :size="17" /> Editar
                </a>
            @endif

            <a href="{{ route('historial-precios.index', ['proveedor_id' => $cotizacion->proveedor_id]) }}"
                class="button button--primary">
                <x-ui.icon name="banknote" :size="17" /> Ver historial
            </a>
        </div>
    </section>

    <section class="supplier-quote-detail-grid">
        <article class="panel supplier-quote-info-panel">
            <header class="supplier-panel-heading">
                <div>
                    <p class="eyebrow">Información del documento</p>
                    <h2>Datos principales</h2>
                </div>
            </header>

            <dl class="supplier-info-grid">
                <div>
                    <dt>Proveedor</dt>
                    <dd>
                        <a href="{{ route('proveedores.show', $cotizacion->proveedor_id) }}">
                            {{ $cotizacion->proveedor->nombreVisible() }}
                        </a>
                    </dd>
                </div>
                <div><dt>RUC</dt><dd>{{ $cotizacion->proveedor->ruc }}</dd></div>
                <div><dt>Documento externo</dt><dd>{{ $cotizacion->numero_documento ?: 'No registrado' }}</dd></div>
                <div><dt>Vigencia</dt><dd>{{ $cotizacion->fecha_validez ? $cotizacion->fecha_validez->format('d/m/Y') : 'No especificada' }}</dd></div>
                <div><dt>Requerimiento</dt><dd>{{ $cotizacion->requisicion?->codigo ?: 'Sin requerimiento' }}</dd></div>
                <div><dt>Registrado por</dt><dd>{{ $cotizacion->registrador?->username ?: 'Usuario no disponible' }}</dd></div>
                <div><dt>Condiciones de pago</dt><dd>{{ $cotizacion->condiciones_pago ?: 'No especificadas' }}</dd></div>
                <div><dt>Condiciones de entrega</dt><dd>{{ $cotizacion->condiciones_entrega ?: 'No especificadas' }}</dd></div>
                @if ($cotizacion->observacion)
                    <div class="supplier-info-grid__wide"><dt>Observación</dt><dd>{{ $cotizacion->observacion }}</dd></div>
                @endif
            </dl>
        </article>

        <article class="panel supplier-quote-total-card">
            <p class="eyebrow">Resumen económico</p>
            <div><span>Subtotal sin IGV</span><strong>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->subtotal, 2) }}</strong></div>
            <div>
                <span>Descuento general</span>
                <strong>
                    @if ($cotizacion->descuento_global_modo === 'INCLUIDO')
                        No detallado
                    @else
                        {{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->descuento_global_monto, 2) }}
                    @endif
                </strong>
            </div>
            <div><span>Base neta</span><strong>{{ $cotizacion->simboloMoneda() }} {{ number_format($cotizacion->baseNeta(), 2) }}</strong></div>
            <div><span>IGV</span><strong>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->impuesto, 2) }}</strong></div>
            <div class="supplier-quote-total-card__main">
                <span>Total</span>
                <strong>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->total, 2) }}</strong>
            </div>
            @if ($cotizacion->moneda === 'USD')
                <small>Tipo de cambio: {{ number_format((float) $cotizacion->tipo_cambio, 2) }}</small>
            @endif
            <small>{{ $cotizacion->descuentoGlobalVisible() }}</small>
        </article>
    </section>

    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading">
            <div>
                <p class="eyebrow">Detalle de precios</p>
                <h2>Productos cotizados</h2>
            </div>
        </header>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Marca ofrecida</th>
                        <th class="text-right">Cantidad</th>
                        <th class="text-right">Precio informado</th>
                        <th>Descuento</th>
                        <th>IGV</th>
                        <th class="text-right">Base sin IGV</th>
                        <th class="text-right">IGV línea</th>
                        <th class="text-right">Total línea</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cotizacion->detalles as $detalle)
                        <tr>
                            <td>
                                <strong>{{ $detalle->producto?->codigo }}</strong>
                                <span>{{ $detalle->producto?->descripcion }}</span>
                            </td>
                            <td>
                                <strong>{{ $detalle->marca_ofertada ?: 'No especificada' }}</strong>
                                @if ($detalle->observacion)<span>{{ $detalle->observacion }}</span>@endif
                            </td>
                            <td class="text-right"><x-ui.quantity :value="$detalle->cantidad" /></td>
                            <td class="text-right">
                                {{ $cotizacion->simboloMoneda() }}
                                {{ number_format((float) $detalle->precio_unitario, 2) }}
                            </td>
                            <td><strong>{{ $detalle->descuentoVisible() }}</strong></td>
                            <td><span>{{ $detalle->igvVisible() }}</span></td>
                            <td class="text-right">
                                {{ $cotizacion->simboloMoneda() }}
                                {{ number_format((float) $detalle->subtotal, 2) }}
                            </td>
                            <td class="text-right">
                                {{ $cotizacion->simboloMoneda() }}
                                {{ number_format((float) $detalle->impuesto, 2) }}
                            </td>
                            <td class="text-right">
                                <strong>
                                    {{ $cotizacion->simboloMoneda() }}
                                    {{ number_format((float) $detalle->total, 2) }}
                                </strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($cotizacion->estaAnulada())
        <section class="notice notice--danger notice--block supplier-quote-cancelled">
            <x-ui.icon name="error" :size="20" />
            <div>
                <strong>Cotización invalidada</strong>
                <p>
                    {{ $cotizacion->motivo_anulacion }}
                    @if ($cotizacion->anulado_en)
                        · {{ $cotizacion->anulado_en->format('d/m/Y H:i') }}
                    @endif
                </p>
            </div>
        </section>
    @elseif ($cotizacion->puedeEditar())
        <section class="supplier-quote-danger-zone">
            <div>
                <p class="eyebrow">Control documental</p>
                <h2>Invalidar por error de registro</h2>
                <p>No la invalides solo porque no fue elegida: en ese caso debe permanecer como referencia histórica.</p>
            </div>

            <form method="POST"
                action="{{ route('cotizaciones-proveedor.anular', $cotizacion->id) }}"
                class="supplier-quote-cancel-form"
                data-confirm="¿Confirmas invalidar esta cotización por un error de registro?">
                @csrf
                @method('PATCH')
                <input type="text" name="motivo_anulacion" maxlength="500"
                    minlength="5" required placeholder="Describe el error de registro">
                <button type="submit" class="button button--danger">
                    <x-ui.icon name="error" :size="17" /> Invalidar
                </button>
            </form>
        </section>
    @endif
@endsection
