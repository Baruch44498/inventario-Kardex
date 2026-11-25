<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orden de Compra {{ $orden->codigo }} · HIDROIL</title>
    <link rel="stylesheet" href="{{ asset('css/hidroil/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/hidroil/cotizacion-documento.css') }}">
    <script src="{{ asset('js/cotizacion-documento.js') }}" defer></script>
</head>
<body>
    <nav class="quote-print-toolbar" aria-label="Acciones del documento">
        <a href="{{ route('ordenes-compra.show', $orden) }}">Volver a la orden</a>
        <button type="button" data-print-quote>Imprimir / guardar como PDF</button>
    </nav>

    <main class="quote-print-sheet">
        <header class="quote-print-header">
            <img src="{{ asset('images/logo-hidroil.png') }}" alt="HIDROIL" class="quote-print-logo">
            <div class="quote-print-identity">
                <p class="quote-print-eyebrow">Documento de compra</p>
                <h1>Orden de Compra {{ $orden->codigo }}</h1>
                <span class="quote-print-state">{{ str_replace('_', ' ', $orden->estado) }}</span>
            </div>
        </header>

        @if ($orden->estaAnulada())
            <p class="quote-print-alert">Orden anulada. Este documento se conserva únicamente como referencia histórica.</p>
        @endif

        <section class="quote-print-meta" aria-label="Datos de la orden">
            <div><span>Proveedor</span><strong>{{ $orden->proveedor?->nombreVisible() ?: 'No registrado' }}</strong></div>
            <div><span>RUC</span><strong>{{ $orden->proveedor?->ruc ?: 'No indicado' }}</strong></div>
            @if ($orden->proveedor?->direccion)
                <div><span>Dirección del proveedor</span><strong>{{ $orden->proveedor->direccion }}</strong></div>
            @endif
            <div><span>Fecha de emisión</span><strong><time datetime="{{ $orden->fecha_emision?->format('Y-m-d') }}">{{ $orden->fecha_emision?->format('d/m/Y') }}</time></strong></div>
            @if ($orden->fecha_entrega_requerida)
                <div><span>Entrega requerida</span><strong><time datetime="{{ $orden->fecha_entrega_requerida->format('Y-m-d') }}">{{ $orden->fecha_entrega_requerida->format('d/m/Y') }}</time></strong></div>
            @endif
            @if ($orden->numero_documento_proveedor)
                <div><span>Documento del proveedor</span><strong>{{ $orden->numero_documento_proveedor }}</strong></div>
            @endif
            <div><span>Moneda</span><strong>{{ $orden->moneda }}</strong></div>
        </section>

        <section class="quote-print-content" aria-labelledby="purchase-print-lines">
            <h2 id="purchase-print-lines">Productos solicitados</h2>
            @if ($orden->detalles->isNotEmpty())
                <div class="quote-print-table-wrap">
                    <table class="quote-print-table quote-print-table--purchase">
                        <thead><tr><th scope="col">Producto</th><th scope="col">Cantidad</th><th scope="col">Unidad</th><th scope="col">P. unitario</th><th scope="col">Desc. %</th><th scope="col">Importe</th></tr></thead>
                        <tbody>
                            @foreach ($orden->detalles as $detalle)
                                <tr>
                                    <td><strong>{{ $detalle->producto?->descripcion ?: 'Producto registrado' }}</strong>@if ($detalle->producto?->codigo)<small>{{ $detalle->producto->codigo }}</small>@endif</td>
                                    <td class="quote-print-number">{{ rtrim(rtrim(number_format((float) $detalle->cantidad_ordenada, 2, '.', ','), '0'), '.') }}</td>
                                    <td>{{ $detalle->producto?->unidadMedida?->codigo ?: '—' }}</td>
                                    <td class="quote-print-number">{{ number_format((float) $detalle->precio_unitario, 2, '.', ',') }}</td>
                                    <td class="quote-print-number">{{ number_format((float) $detalle->descuento_porcentaje, 2, '.', ',') }}</td>
                                    <td class="quote-print-number">{{ number_format((float) $detalle->subtotal, 2, '.', ',') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p>No hay productos asociados a esta orden.</p>
            @endif
        </section>

        <section class="quote-print-total" aria-label="Importes de la orden">
            <dl>
                <div><dt>Subtotal</dt><dd>{{ $orden->moneda }} {{ number_format((float) $orden->subtotal, 2, '.', ',') }}</dd></div>
                <div><dt>IGV</dt><dd>{{ $orden->moneda }} {{ number_format((float) $orden->impuesto, 2, '.', ',') }}</dd></div>
                @if (abs((float) $orden->ajuste_redondeo) >= 0.005)
                    <div><dt>Ajuste de redondeo</dt><dd>{{ $orden->moneda }} {{ number_format((float) $orden->ajuste_redondeo, 2, '.', ',') }}</dd></div>
                @endif
                <div class="quote-print-total-main"><dt>Total de la orden</dt><dd>{{ $orden->moneda }} {{ number_format((float) $orden->total, 2, '.', ',') }}</dd></div>
            </dl>
        </section>

        @if ($orden->condiciones_pago || $orden->condiciones_entrega)
            <section class="quote-print-terms" aria-label="Condiciones de compra">
                @if ($orden->condiciones_pago)<div><h2>Condiciones de pago</h2><p>{{ $orden->condiciones_pago }}</p></div>@endif
                @if ($orden->condiciones_entrega)<div><h2>Condiciones de entrega</h2><p>{{ $orden->condiciones_entrega }}</p></div>@endif
            </section>
        @endif
        <footer class="quote-print-footer">HIDROIL · Orden de Compra {{ $orden->codigo }}</footer>
    </main>
</body>
</html>
