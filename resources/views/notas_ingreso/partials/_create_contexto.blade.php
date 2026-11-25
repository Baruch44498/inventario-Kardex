<section class="order-context-card" data-note-origin-context>
    <div class="order-context-card__main">
        <span class="order-context-card__icon"><x-ui.icon name="entry" :size="25" /></span>
        <div>
            <span>Origen seleccionado</span>
            @if ($orden)
                <strong>{{ $orden->codigo }}</strong>
                <small>{{ $orden->proveedor?->razon_social ?? 'Proveedor no disponible' }}</small>
            @elseif ($notaSalida)
                <strong>{{ $notaSalida->codigo }}</strong>
                <small>{{ $notaSalida->entregado_a ?: 'Sin receptor registrado' }}</small>
            @else
                <strong>{{ $proforma->codigo }}</strong>
                <small>{{ $proforma->cliente?->nombreVisible() ?? 'Sin cliente' }}</small>
            @endif
        </div>
    </div>
    <dl class="order-context-card__facts">
        <div><dt>Tipo</dt><dd>{{ match($motivo) { 'COMPRA' => 'Compra', 'DEVOLUCION_HERRAMIENTA' => 'Devolución de herramienta', 'RETORNO_MATERIAL' => 'Retorno de material', 'DEVOLUCION_MATERIAL_MALOGRADO' => 'Material malogrado', default => 'Reposición de préstamo' } }}</dd></div>
        @if ($notaSalida?->ordenOperacion)
            <div><dt>Orden</dt><dd>{{ $notaSalida->ordenOperacion->codigo_orden }}</dd></div>
            <div><dt>Área</dt><dd>{{ $notaSalida->area_trabajo ?: 'GENERAL' }}</dd></div>
        @endif
        <div><dt>Pendientes</dt><dd>{{ $filas->count() }} línea(s)</dd></div>
        @if ($orden)
            <div><dt>Estado actual</dt><dd>{{ $orden->estadoVisible() }}</dd></div>
            <div><dt>Valoración</dt><dd>{{ $facturaSeleccionada ? 'Factura real' : 'OC provisional' }} · Soles, IGV incluido</dd></div>
        @endif
    </dl>
</section>
