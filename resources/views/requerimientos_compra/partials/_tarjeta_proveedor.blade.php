@php
    $esDistribucion = $tipo === 'distribucion';
    $productos = $registro['productos'];
    $initials = collect(explode(' ', $registro['nombre']))
        ->take(2)
        ->map(fn ($palabra) => mb_strtoupper(mb_substr($palabra, 0, 1)))
        ->implode('');
@endphp

<article class="prc-supplier-card prc-supplier-card--confirmed">
    <div class="prc-supplier-card__header">
        <div class="prc-supplier-avatar" aria-hidden="true">{{ $initials }}</div>
        <div class="prc-supplier-card__identity">
            <strong class="prc-supplier-card__name">{{ $registro['nombre'] }}</strong>
            <span class="prc-supplier-card__ruc">RUC&nbsp;{{ $registro['ruc'] }}</span>
        </div>
        <span class="prc-supplier-card__badge badge badge--info">
            @if ($esDistribucion)
                Lista&nbsp;{{ $numeroLista }}&nbsp;&middot;&nbsp;{{ $productos->count() }}&nbsp;prod.
            @else
                {{ $productos->count() }}&nbsp;producto{{ $productos->count() === 1 ? '' : 's' }}
            @endif
        </span>
    </div>

    <div class="prc-supplier-card__body">
        <p class="prc-supplier-card__label">
            {{ $esDistribucion ? 'Productos asignados' : 'Productos relacionados' }}
        </p>
        <div class="prc-product-chips">
            @foreach ($productos as $producto)
                <span class="prc-product-chip">
                    {{ $esDistribucion ? Str::before($producto, ' —') : $producto }}
                </span>
            @endforeach
        </div>
    </div>

    <div class="prc-supplier-card__contact-row">
        @if ($registro['telefono'])
            <span class="prc-contact-item">
                <x-ui.icon name="phone" :size="12" />
                {{ $registro['telefono'] }}
            </span>
        @endif
        @if ($registro['correo'])
            <span class="prc-contact-item">
                <x-ui.icon name="mail" :size="12" />
                {{ $registro['correo'] }}
            </span>
        @endif
        @if ($esDistribucion && $registro['ultima_cotizacion'])
            <span class="prc-contact-item prc-contact-item--muted">
                <x-ui.icon name="calendar" :size="12" />
                Últ. ref. {{ \Illuminate\Support\Carbon::parse($registro['ultima_cotizacion'])->format('d/m/Y') }}
            </span>
        @elseif (! $esDistribucion && $registro['contacto'])
            <span class="prc-contact-item prc-contact-item--muted">
                <x-ui.icon name="user" :size="12" />
                {{ $registro['contacto'] }}
            </span>
        @endif
    </div>

    @if ($esDistribucion && ($puedeDescargarSolicitud || $puedeGestionar))
        <div class="prc-supplier-card__footer prc-supplier-card__footer--actions">
            @if ($puedeDescargarSolicitud)
                <a href="{{ route('requerimientos-compra.solicitud-cotizacion.excel', [
                    'requerimientoCompra' => $requerimiento,
                    'proveedor' => $registro['proveedor_id'],
                    'detalle_ids' => $registro['detalle_ids']->all(),
                ]) }}" class="button button--ghost button--small" title="Descargar solicitud Excel">
                    <x-ui.icon name="entry" :size="15" /> Excel
                </a>
            @endif
            @if ($puedeGestionar)
                <a href="{{ route('cotizaciones-proveedor.create', [
                    'requisicion_id' => $requerimiento->id,
                    'proveedor_id' => $registro['proveedor_id'],
                    'detalle_ids' => $registro['detalle_ids']->all(),
                ]) }}" class="button button--primary button--small">
                    <x-ui.icon name="quotes" :size="15" /> Cotizar esta lista
                </a>
            @endif
        </div>
    @elseif (! $esDistribucion)
        <div class="prc-supplier-card__footer prc-supplier-card__footer--actions">
            @if ($registro['telefono'])
                <a href="tel:{{ preg_replace('/\s+/', '', $registro['telefono']) }}" class="button button--ghost button--small">
                    <x-ui.icon name="phone" :size="15" /> Llamar
                </a>
            @endif
            @if ($registro['correo'])
                <a href="mailto:{{ $registro['correo'] }}" class="button button--ghost button--small">
                    <x-ui.icon name="mail" :size="15" /> Correo
                </a>
            @endif
            @if ($puedeGestionar)
                <a href="{{ route('cotizaciones-proveedor.create', [
                    'requisicion_id' => $requerimiento->id,
                    'proveedor_id' => $registro['proveedor_id'],
                    'detalle_ids' => $registro['detalle_ids']->all(),
                ]) }}" class="button button--primary button--small">
                    <x-ui.icon name="quotes" :size="15" /> Cotizar con este
                </a>
            @endif
        </div>
    @endif
</article>
