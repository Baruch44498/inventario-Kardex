<a href="{{ route('requerimientos-compra.index') }}" class="back-link">
    <x-ui.icon name="arrow-left" :size="17" /> Volver a requerimientos
</a>

<section class="module-header purchase-requirement-show-header">
    <div class="purchase-requirement-header-copy">
        <p class="eyebrow">Almacén → Logística</p>
        <h1>{{ $requerimiento->codigo }}</h1>
        <p>{{ $requerimiento->descripcion ?: 'Necesidad de abastecimiento registrada por Almacén.' }}</p>

        <x-ui.next-action
            :title="$siguienteAccion['titulo']"
            :description="$siguienteAccion['detalle']"
            :tone="$siguienteAccion['tono']"
            :href="! $puedeEditar ? $siguienteAccion['ruta'] : null"
            :label="! $puedeEditar ? $siguienteAccion['boton'] : null"
            class="purchase-requirement-next-action purchase-requirement-next-action--{{ $siguienteAccion['tono'] }}"
            icon-class="purchase-requirement-next-action__icon"
            link-class="purchase-requirement-next-action__link"
        />
    </div>
    <div class="purchase-requirement-header-actions">
        <span class="badge badge--{{ $prioridadClase }}">{{ $requerimiento->prioridad }}</span>
        <span class="badge badge--{{ $estadoClase }}">Gestión: {{ str($requerimiento->estado)->replace('_', ' ')->title() }}</span>
        <span class="badge badge--{{ $abastecimientoClase }}">{{ $requerimiento->estadoAbastecimientoVisible() }}</span>

        <a href="{{ route('requerimientos-compra.excel', $requerimiento) }}"
            class="button button--ghost" data-file-download>
            <x-ui.icon name="entry" :size="17" /> Exportar requerimiento
        </a>

        @if ((auth()->user()->puede('compras.gestionar') || auth()->user()->esAdministrador()) && $requerimiento->cotizaciones_count > 0 && in_array($requerimiento->estado, ['EN_REVISION', 'COTIZANDO', 'ATENDIDA'], true))
            <a href="{{ route('requerimientos-compra.comparativo', $requerimiento) }}" class="button button--primary">
                <x-ui.icon name="banknote" :size="17" /> Comparar ofertas
            </a>
        @endif

        @if ($puedeEditar)
            <a href="{{ route('requerimientos-compra.edit', $requerimiento) }}" class="button button--ghost"><x-ui.icon name="edit" :size="17" /> Editar</a>
            <form method="POST" action="{{ route('requerimientos-compra.enviar', $requerimiento) }}" data-loading-form>
                @csrf @method('PATCH')
                <button class="button button--primary" type="submit" data-submit-button data-loading-text="Enviando...">
                    <span data-submit-icon><x-ui.icon name="mail" :size="17" /></span>
                    <span class="button-spinner" data-submit-spinner hidden></span>
                    <span data-submit-label>Enviar a Logística</span>
                </button>
            </form>
        @endif
    </div>
</section>

<section class="summary-strip summary-strip--four purchase-requirement-summary" aria-label="Datos del requerimiento">
    <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--info"><x-ui.icon name="calendar" :size="20" /></span><div><span>Fecha</span><strong>{{ $requerimiento->fecha_solicitud?->format('d/m/Y') }}</strong></div></article>
    <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--neutral"><x-ui.icon name="orders" :size="20" /></span><div><span>Origen</span><strong>{{ $requerimiento->ordenOperacion?->codigo_orden ?: 'Reposición' }}</strong></div></article>
    <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--neutral"><x-ui.icon name="user" :size="20" /></span><div><span>Solicitado por</span><strong>{{ $requerimiento->solicitante?->nombreVisible() ?? '—' }}</strong></div></article>
    <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--info"><x-ui.icon name="quotes" :size="20" /></span><div><span>Cotizaciones vinculadas</span><strong>{{ (int) $requerimiento->cotizaciones_count }}</strong></div></article>
</section>

@if ($requerimiento->estaAnulada())
    <div class="notice notice--danger notice--block" role="status">
        <x-ui.icon name="error" :size="19" />
        <div>
            <strong>Requerimiento anulado</strong>
            <p>{{ $requerimiento->motivo_anulacion }} · {{ $requerimiento->anulado_en?->format('d/m/Y H:i') }}</p>
        </div>
    </div>
@endif

@if ($requerimiento->alertasStock->isNotEmpty())
    <div class="notice notice--info notice--block" role="note">
        <x-ui.icon name="bell" :size="19" />
        <div>
            <strong>{{ $requerimiento->alertasStock->count() }} alerta{{ $requerimiento->alertasStock->count() === 1 ? '' : 's' }} vinculada{{ $requerimiento->alertasStock->count() === 1 ? '' : 's' }}</strong>
            <p>Este requerimiento mantiene la trazabilidad desde las alertas de stock hasta las compras y recepciones registradas.</p>
        </div>
        <a href="{{ route('alertas.index') }}" class="button button--ghost button--small">Ver alertas</a>
    </div>
@endif

@if ($requerimiento->abastecimientoCompleto())
    <div class="notice notice--success notice--block" role="status">
        <x-ui.icon name="check-circle" :size="19" />
        <div>
            <strong>Abastecimiento completado con recepciones reales</strong>
            <p>Todos los productos solicitados alcanzaron su cantidad requerida{{ $requerimiento->abastecido_en ? ' el '.$requerimiento->abastecido_en->format('d/m/Y H:i') : '' }}.</p>
        </div>
    </div>
@endif
