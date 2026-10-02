<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cotización {{ $cotizacion->codigo }} · HIDROIL</title>
    <link rel="stylesheet" href="{{ asset('css/hidroil/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/hidroil/cotizacion-documento.css') }}">
    <script src="{{ asset('js/cotizacion-documento.js') }}" defer></script>
</head>
<body>
    @php
        $detalles = $mostrarDetalle ? $cotizacion->detalles : collect();
        $otrosConceptos = $detalles->isNotEmpty()
            ? round((float) $cotizacion->total - (float) $detalles->sum('total'), 2)
            : 0;
        $estado = match ($cotizacion->estado) {
            'ABIERTA' => 'BORRADOR',
            'ANULADA' => 'ANULADA',
            'CONVERTIDA_EN_ORDEN' => 'APROBADA',
            default => 'CERRADA',
        };
    @endphp

    <nav class="quote-print-toolbar" aria-label="Acciones del documento">
        <a href="{{ route('cotizaciones-cliente.show', $cotizacion) }}">Volver a la cotización</a>
        <button type="button" data-print-quote>Imprimir / guardar como PDF</button>
    </nav>

    <main class="quote-print-sheet">
        <header class="quote-print-header">
            <img src="{{ asset('images/logo-hidroil.png') }}" alt="HIDROIL" class="quote-print-logo">
            <div class="quote-print-identity">
                <p class="quote-print-eyebrow">Documento comercial · Versión {{ $cotizacion->version }}</p>
                <h1>Cotización {{ $cotizacion->codigo }}</h1>
                <span class="quote-print-state">{{ $estado }}</span>
            </div>
        </header>

        @if ($cotizacion->estado === 'ABIERTA')
            <p class="quote-print-alert">Borrador sujeto a revisión. Los importes aún pueden cambiar.</p>
        @elseif ($cotizacion->estado === 'ANULADA')
            <p class="quote-print-alert">Documento anulado. Se conserva únicamente como referencia histórica.</p>
        @endif

        <section class="quote-print-meta" aria-label="Datos de la cotización">
            <div><span>Cliente</span><strong>{{ $cotizacion->cliente_nombre }}</strong></div>
            <div><span>Documento</span><strong>{{ $cotizacion->cliente_documento ?: 'No indicado' }}</strong></div>
            @if ($cotizacion->clienteDireccion?->direccion)
                <div><span>Dirección</span><strong>{{ $cotizacion->clienteDireccion->direccion }}</strong></div>
            @endif
            <div><span>Fecha de emisión</span><strong><time datetime="{{ $cotizacion->fecha_emision?->format('Y-m-d') }}">{{ $cotizacion->fecha_emision?->format('d/m/Y') }}</time></strong></div>
            @if ($cotizacion->fecha_validez)
                <div><span>Válida hasta</span><strong><time datetime="{{ $cotizacion->fecha_validez->format('Y-m-d') }}">{{ $cotizacion->fecha_validez->format('d/m/Y') }}</time></strong></div>
            @endif
            <div><span>Moneda</span><strong>{{ $cotizacion->moneda }}</strong></div>
            @if ($codigoTipo)
                <div><span>Tipo de trabajo</span><strong>{{ $codigoTipo }}</strong></div>
            @endif
        </section>

        <section class="quote-print-content" aria-labelledby="quote-print-work">
            <h2 id="quote-print-work">Propuesta</h2>
            @if ($cotizacion->descripcion_trabajo)
                <p class="quote-print-description">{{ $cotizacion->descripcion_trabajo }}</p>
            @endif

            @if ($detalles->isNotEmpty())
                <div class="quote-print-table-wrap">
                    <table class="quote-print-table">
                        <thead><tr><th scope="col">Concepto</th><th scope="col">Cantidad</th><th scope="col">Unidad</th><th scope="col">P. unitario</th><th scope="col">Importe</th></tr></thead>
                        <tbody>
                            @foreach ($detalles as $detalle)
                                <tr>
                                    <td><strong>{{ $detalle->descripcion ?: $detalle->codigo_producto }}</strong>@if ($detalle->codigo_producto && $detalle->tipo_linea === 'PRODUCTO')<small>{{ $detalle->codigo_producto }}</small>@endif</td>
                                    <td class="quote-print-number">{{ rtrim(rtrim(number_format((float) $detalle->cantidad, 2, '.', ','), '0'), '.') }}</td>
                                    <td>{{ $detalle->unidad_medida }}</td>
                                    <td class="quote-print-number">{{ number_format((float) $detalle->precio_unitario, 2, '.', ',') }}</td>
                                    <td class="quote-print-number">{{ number_format((float) $detalle->total, 2, '.', ',') }}</td>
                                </tr>
                            @endforeach
                            @if ($otrosConceptos > 0.01)
                                <tr><td colspan="4">Otros conceptos incluidos en la propuesta</td><td class="quote-print-number">{{ number_format($otrosConceptos, 2, '.', ',') }}</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            @else
                <div class="quote-print-summary-line">
                    <span>{{ $cotizacion->descripcion_trabajo ?: 'Trabajo o servicio cotizado' }}</span>
                    <strong>{{ $cotizacion->moneda }} {{ number_format((float) $cotizacion->total, 2, '.', ',') }}</strong>
                </div>
            @endif
        </section>

        <section class="quote-print-total" aria-label="Importes de la cotización">
            <dl>
                <div><dt>Subtotal</dt><dd>{{ $cotizacion->moneda }} {{ number_format((float) $cotizacion->subtotal, 2, '.', ',') }}</dd></div>
                <div><dt>IGV</dt><dd>{{ $cotizacion->moneda }} {{ number_format((float) $cotizacion->impuesto, 2, '.', ',') }}</dd></div>
                <div class="quote-print-total-main"><dt>Total</dt><dd>{{ $cotizacion->moneda }} {{ number_format((float) $cotizacion->total, 2, '.', ',') }}</dd></div>
            </dl>
        </section>

        @if ($cotizacion->condiciones_pago || $cotizacion->condiciones_entrega)
            <section class="quote-print-terms" aria-label="Condiciones comerciales">
                @if ($cotizacion->condiciones_pago)<div><h2>Condiciones de pago</h2><p>{{ $cotizacion->condiciones_pago }}</p></div>@endif
                @if ($cotizacion->condiciones_entrega)<div><h2>Condiciones de entrega</h2><p>{{ $cotizacion->condiciones_entrega }}</p></div>@endif
            </section>
        @endif
        <footer class="quote-print-footer">HIDROIL · {{ $cotizacion->codigo }} · VRS{{ $cotizacion->version }}</footer>
    </main>
</body>
</html>
