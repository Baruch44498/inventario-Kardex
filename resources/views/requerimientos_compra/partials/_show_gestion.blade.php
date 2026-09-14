@if ($requerimiento->estado !== 'BORRADOR')
    <section class="panel purchase-requirement-workflow-panel">
        <div class="panel-heading purchase-requirement-section-heading">
            <div class="purchase-requirement-section-heading__copy">
                <p class="eyebrow">Seguimiento</p>
                <div class="purchase-requirement-section-heading__title-row">
                    <h2>Atención de Logística</h2>
                </div>
                <p class="purchase-requirement-section-heading__description">Responsable, etapa actual y siguiente acción del requerimiento.</p>
            </div>
            <div class="purchase-requirement-section-heading__meta">
                <span class="badge badge--{{ $estadoClase }}">{{ str($requerimiento->estado)->replace('_', ' ')->title() }}</span>
            </div>
        </div>

        <div class="purchase-requirement-workflow-grid">
            <article class="purchase-requirement-workflow-card">
                <span>Responsable de Logística</span>
                <strong>{{ $requerimiento->receptor?->nombreVisible() ?? ($requerimiento->estado === 'ENVIADA' ? 'Pendiente de asignar' : '—') }}</strong>
                <small>
                    @if ($requerimiento->recibido_en)
                        Tomado el {{ $requerimiento->recibido_en->format('d/m/Y H:i') }}
                    @else
                        Logística debe tomar el requerimiento para iniciar su atención.
                    @endif
                </small>
            </article>

            <article class="purchase-requirement-workflow-card">
                <span>Enviado por Almacén</span>
                <strong>{{ $requerimiento->enviador?->nombreVisible() ?? '—' }}</strong>
                <small>{{ $requerimiento->enviado_en?->format('d/m/Y H:i') ?? 'Sin fecha registrada' }}</small>
            </article>

            @if ($requerimiento->estaAtendida())
                <article class="purchase-requirement-workflow-card">
                    <span>Atendido por</span>
                    <strong>{{ $requerimiento->atendidoPor?->nombreVisible() ?? '—' }}</strong>
                    <small>{{ $requerimiento->atendido_en?->format('d/m/Y H:i') ?? 'Sin fecha registrada' }}</small>
                </article>
            @endif

            <article class="purchase-requirement-workflow-card">
                <span>Abastecimiento físico</span>
                <strong>{{ $requerimiento->estadoAbastecimientoVisible() }}</strong>
                <small>
                    {{ $requerimiento->abastecido_en
                        ? 'Completado el '.$requerimiento->abastecido_en->format('d/m/Y H:i')
                        : 'Se completa únicamente con notas de ingreso confirmadas.' }}
                </small>
            </article>
        </div>

        @if ($puedeGestionar && in_array($requerimiento->estado, ['ENVIADA', 'EN_REVISION'], true))
            @php
                $accionSeguimiento = match ($requerimiento->estado) {
                    'ENVIADA' => [
                        'ruta' => route('requerimientos-compra.recibir', $requerimiento),
                        'texto' => 'Tomar para revisión',
                        'icono' => 'check',
                        'ayuda' => 'Al tomarlo quedas registrado como responsable inicial de Logística.',
                    ],
                    'EN_REVISION' => [
                        'ruta' => route('requerimientos-compra.cotizando', $requerimiento),
                        'texto' => 'Iniciar cotización',
                        'icono' => 'quotes',
                        'ayuda' => 'Marca que Logística ya inició el contacto y solicitud de precios a proveedores.',
                    ],
                };
            @endphp

            <form id="gestion-logistica" method="POST" action="{{ $accionSeguimiento['ruta'] }}" class="purchase-requirement-followup-form" data-loading-form>
                @csrf
                @method('PATCH')
                <label class="form-field purchase-requirement-followup-form__note">
                    <span>Nota de seguimiento <small>(opcional)</small></span>
                    <textarea name="observacion_seguimiento" rows="2" maxlength="500" placeholder="Ej.: Se contactará primero a los proveedores con disponibilidad inmediata.">{{ old('observacion_seguimiento') }}</textarea>
                </label>
                <div class="purchase-requirement-followup-form__action">
                    <small>{{ $accionSeguimiento['ayuda'] }}</small>
                    <button class="button button--primary" type="submit" data-submit-button data-loading-text="Guardando...">
                        <span data-submit-icon><x-ui.icon :name="$accionSeguimiento['icono']" :size="17" /></span>
                        <span class="button-spinner" data-submit-spinner hidden></span>
                        <span data-submit-label>{{ $accionSeguimiento['texto'] }}</span>
                    </button>
                </div>
            </form>
        @endif
    </section>
@endif

@if ($puedeAnular)
    <section class="supplier-quote-danger-zone purchase-requirement-document-control">
        <div>
            <p class="eyebrow">Control documental</p>
            <h2>Anular requerimiento</h2>
            <p>La anulación conserva el historial y libera las alertas vinculadas. Solo está permitida antes de generar una orden de compra vigente.</p>
        </div>
        <form
            method="POST"
            action="{{ route('requerimientos-compra.anular', $requerimiento) }}"
            class="supplier-quote-cancel-form"
            data-confirm="¿Confirmas anular este requerimiento y liberar sus alertas?"
            data-confirm-title="Anular requerimiento"
            data-confirm-label="Anular requerimiento"
        >
            @csrf
            @method('PATCH')
            <input
                type="text"
                name="motivo_anulacion"
                value="{{ old('motivo_anulacion') }}"
                minlength="10"
                maxlength="500"
                required
                placeholder="Explica por qué se cancela la necesidad"
            >
            @error('motivo_anulacion')<small class="field-error">{{ $message }}</small>@enderror
            <button class="button button--danger" type="submit">
                <x-ui.icon name="error" :size="17" /> Anular requerimiento
            </button>
        </form>
    </section>
@elseif ($tieneOrdenCompraActiva && ! $requerimiento->estaAnulada())
    <x-ui.collapsible-notice class="purchase-requirement-document-control" title="La anulación está bloqueada" label="Ver motivo">
        <span>Este requerimiento ya generó una orden de compra vigente. Para cancelarlo deben anularse primero sus órdenes relacionadas; una OC con recepción registrada no puede revertirse.</span>
    </x-ui.collapsible-notice>
@endif
