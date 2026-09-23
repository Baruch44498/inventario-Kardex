@extends('layouts.app')

@section('title', $inventarioPeriodico->codigo)
@section('page-kicker', 'Almacén')
@section('page-title', 'Detalle del inventario periódico')

@section('content')
    @php
        $puedeGestionar = auth()->user()->puede('inventario.configurar');
        $estaAbierto = $inventarioPeriodico->estado === 'ABIERTO';
        $tono = match ($inventarioPeriodico->estado) {
            'CERRADO' => 'success',
            'ANULADO' => 'danger',
            default => 'warning',
        };
        $conteoCompleto = (int) $inventarioPeriodico->total_lineas > 0
            && $lineasContadas >= (int) $inventarioPeriodico->total_lineas;
        $vistaInicial = ! $estaAbierto
            ? 'trace'
            : (($errors->any() && old('motivo_anulacion') !== null)
                ? 'cancel'
                : (($errors->any() && old('detalles') !== null)
                    ? 'count'
                    : (($conteoCompleto && $puedeGestionar) ? 'close' : 'count')));
    @endphp

    <div class="periodic-inventory-show-page">

    <section class="module-header">
        <div>
            <p class="eyebrow">Conteo físico por repisa</p>
            <h1>{{ $inventarioPeriodico->codigo }}</h1>
            <p>
                Repisa {{ $inventarioPeriodico->repisa?->codigo }} · corte
                {{ $inventarioPeriodico->fecha_corte?->format('d/m/Y H:i') }}.
            </p>
        </div>
        <div class="module-header__actions">
            <x-ui.status-badge :tone="$tono">{{ str($inventarioPeriodico->estado)->title() }}</x-ui.status-badge>
            <a href="{{ route('inventarios-periodicos.index') }}" class="button button--ghost">Volver</a>
        </div>
    </section>

    @if ($errors->any())
        <div class="notice notice--danger notice--block">
            <x-ui.icon name="warning" :size="19" />
            <div>
                <strong>No se pudo completar la acción</strong>
                <p>{{ $errors->first() }}</p>
            </div>
        </div>
    @endif

    @if ($estaAbierto)
        <div class="notice notice--warning notice--block">
            <x-ui.icon name="warning" :size="19" />
            <div>
                <strong>Conteo en curso</strong>
                <p>Guarda el avance antes de cerrar. Si esta repisa recibe una entrada o salida, el cierre será bloqueado y deberá iniciarse un conteo nuevo.</p>
            </div>
        </div>
    @elseif ($inventarioPeriodico->estado === 'ANULADO')
        <div class="notice notice--danger notice--block">
            <x-ui.icon name="error" :size="19" />
            <div>
                <strong>Conteo anulado sin mover existencias</strong>
                <p>{{ $inventarioPeriodico->motivo_anulacion }}</p>
            </div>
        </div>
    @endif

    <section class="summary-strip" aria-label="Resumen del conteo">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info"><x-ui.icon name="products" :size="21" /></span>
            <div><span>Productos</span><strong>{{ (int) $inventarioPeriodico->total_lineas }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--warning"><x-ui.icon name="edit" :size="21" /></span>
            <div><span>Contados</span><strong>{{ $lineasContadas }}/{{ (int) $inventarioPeriodico->total_lineas }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--danger"><x-ui.icon name="warning" :size="21" /></span>
            <div><span>Con diferencia</span><strong>{{ (int) $inventarioPeriodico->lineas_con_diferencia }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success"><x-ui.icon name="coins" :size="21" /></span>
            <div><span>Valor del sistema</span><strong><x-ui.money :value="$inventarioPeriodico->valor_sistema_soles" /></strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--accent"><x-ui.icon name="banknote" :size="21" /></span>
            <div><span>Diferencia valorizada</span><strong><x-ui.money :value="$inventarioPeriodico->valor_diferencia_soles" /></strong></div>
        </article>
    </section>

    <nav class="periodic-inventory-stepper" aria-label="Etapas y acciones del inventario" role="tablist">
        <button type="button" class="periodic-inventory-step {{ $conteoCompleto ? 'periodic-inventory-step--complete' : '' }} {{ $vistaInicial === 'count' ? 'periodic-inventory-step--active' : '' }}" data-periodic-step="count" role="tab" aria-selected="{{ $vistaInicial === 'count' ? 'true' : 'false' }}">
            <span class="periodic-inventory-step__number">{{ $conteoCompleto ? '✓' : '1' }}</span>
            <span><strong>Conteo físico</strong><small>Registrar y guardar cantidades</small></span>
        </button>
        @if ($estaAbierto && $puedeGestionar)
            <button type="button" class="periodic-inventory-step {{ $vistaInicial === 'close' ? 'periodic-inventory-step--active' : '' }}" data-periodic-step="close" role="tab" aria-selected="{{ $vistaInicial === 'close' ? 'true' : 'false' }}">
                <span class="periodic-inventory-step__number">2</span>
                <span><strong>Cerrar inventario</strong><small>Revisar y aplicar diferencias</small></span>
            </button>
        @endif
        <button type="button" class="periodic-inventory-step {{ ! $estaAbierto ? 'periodic-inventory-step--complete' : '' }} {{ $vistaInicial === 'trace' ? 'periodic-inventory-step--active' : '' }}" data-periodic-step="trace" role="tab" aria-selected="{{ $vistaInicial === 'trace' ? 'true' : 'false' }}">
            <span class="periodic-inventory-step__number">{{ ! $estaAbierto ? '✓' : '3' }}</span>
            <span><strong>Trazabilidad</strong><small>Consultar responsables y fechas</small></span>
        </button>
        @if ($estaAbierto && $puedeGestionar)
            <button type="button" class="periodic-inventory-step periodic-inventory-step--danger {{ $vistaInicial === 'cancel' ? 'periodic-inventory-step--active' : '' }}" data-periodic-step="cancel" role="tab" aria-selected="{{ $vistaInicial === 'cancel' ? 'true' : 'false' }}">
                <span class="periodic-inventory-step__number">×</span>
                <span><strong>Anular</strong><small>Descartar sin mover stock</small></span>
            </button>
        @endif
    </nav>

    <div class="periodic-inventory-panel" data-periodic-panel="count" @if ($vistaInicial !== 'count') hidden @endif>
    @if ($estaAbierto && $puedeGestionar)
        <form method="POST" action="{{ route('inventarios-periodicos.conteo', $inventarioPeriodico) }}" data-dirty-form>
            @csrf
            @method('PATCH')
    @endif

    <section class="panel periodic-inventory-count-panel">
        <div class="panel-heading panel-heading--split">
            <div>
                <p class="eyebrow">Productos de la repisa</p>
                <h2>{{ $estaAbierto ? 'Registrar conteo físico' : 'Resultado del conteo' }}</h2>
                <p>El costo mostrado es la fotografía contable tomada al abrir el inventario.</p>
            </div>
            <span class="count-chip">{{ $inventarioPeriodico->detalles->count() }}</span>
        </div>

        <div class="table-wrap table-wrap--responsive periodic-inventory-detail-wrap" data-responsive-table>
            <table class="data-table data-table--responsive data-table--periodic-inventory periodic-inventory-detail">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="text-right">Stock sistema</th>
                        <th class="text-right">Conteo físico</th>
                        <th>Diferencia / valor</th>
                        <th>Estado</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($inventarioPeriodico->detalles as $detalle)
                        @php
                            $conteoAnterior = old(
                                "detalles.{$detalle->id}.stock_contado",
                                $detalle->stock_contado
                            );
                            $observacionAnterior = old(
                                "detalles.{$detalle->id}.observacion",
                                $detalle->observacion
                            );
                            $detailsId = 'inventario-periodico-linea-' . $detalle->id;
                        @endphp
                        <tr>
                            <td data-label="Producto" class="inventory-flow-product">
                                <strong>{{ $detalle->producto?->codigo }}</strong>
                                <span>{{ $detalle->producto?->descripcion }}</span>
                                <small>{{ $detalle->producto?->unidadMedida?->codigo }}</small>
                            </td>
                            <td data-label="Stock sistema" class="text-right">
                                <strong><x-ui.quantity :value="$detalle->stock_sistema" /></strong>
                                <span>{{ $detalle->producto?->unidadMedida?->codigo }}</span>
                            </td>
                            <td data-label="Conteo físico" class="text-right">
                                @if ($estaAbierto && $puedeGestionar)
                                    <input
                                        type="number"
                                        name="detalles[{{ $detalle->id }}][stock_contado]"
                                        value="{{ $conteoAnterior }}"
                                        min="0"
                                        max="99999999999.999"
                                        step="0.001"
                                        inputmode="decimal"
                                        aria-label="Conteo físico de {{ $detalle->producto?->codigo }}"
                                    >
                                @elseif ($detalle->stock_contado !== null)
                                    <strong><x-ui.quantity :value="$detalle->stock_contado" /></strong>
                                @else
                                    <span class="text-muted">No contado</span>
                                @endif
                            </td>
                            <td data-label="Diferencia / valor" class="periodic-inventory-difference">
                                <strong><x-ui.quantity :value="$detalle->diferencia" /> {{ $detalle->producto?->unidadMedida?->codigo }}</strong>
                                <span><x-ui.money :value="$detalle->valor_diferencia_soles" /></span>
                            </td>
                            <td data-label="Estado">
                                @if ($detalle->stock_contado === null)
                                    <span class="badge badge--neutral">Pendiente</span>
                                @elseif (abs((float) $detalle->diferencia) > 0.0001)
                                    <span class="badge badge--warning">Con diferencia</span>
                                @else
                                    <span class="badge badge--success">Coincide</span>
                                @endif
                            </td>
                            <td data-label="Detalle">
                                <x-ui.table-details-toggle :target="$detailsId" label="Ver costo, ubicación y observación" />
                            </td>
                        </tr>
                        <x-ui.table-row-details :id="$detailsId" :colspan="6">
                            <dl class="table-details-grid inventory-audit-details periodic-inventory-line-details">
                                <div><dt>Repisa</dt><dd><span class="location-chip"><x-ui.icon name="shelf" :size="14" />{{ $inventarioPeriodico->repisa?->codigo }}</span></dd></div>
                                <div><dt>Costo promedio</dt><dd><x-ui.money :value="$detalle->costo_promedio_soles" /></dd></div>
                                <div><dt>Valor del sistema</dt><dd><x-ui.money :value="(float) $detalle->stock_sistema * (float) $detalle->costo_promedio_soles" /></dd></div>
                                <div class="periodic-inventory-line-details__observation">
                                    <dt>Observación</dt>
                                    <dd>
                                        @if ($estaAbierto && $puedeGestionar)
                                            <input type="text" name="detalles[{{ $detalle->id }}][observacion]" value="{{ $observacionAnterior }}" maxlength="300" placeholder="Opcional" aria-label="Observación de {{ $detalle->producto?->codigo }}">
                                        @else
                                            {{ $detalle->observacion ?: '—' }}
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </x-ui.table-row-details>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($estaAbierto && $puedeGestionar)
            <div class="form-actions form-actions--sticky">
                <a href="{{ route('inventarios-periodicos.index') }}" class="button button--ghost">Volver al listado</a>
                <button type="submit" class="button button--primary">
                    <x-ui.icon name="save" :size="17" /> Guardar avance
                </button>
            </div>
        </form>
    @endif
    </div>

    @if ($estaAbierto && $puedeGestionar)
        <section class="panel action-panel periodic-inventory-close-panel periodic-inventory-panel" data-periodic-panel="close" @if ($vistaInicial !== 'close') hidden @endif>
            <div class="panel-heading panel-heading--split">
                <div>
                    <p class="eyebrow">Finalizar conteo</p>
                    <h2>Cerrar y aplicar diferencias</h2>
                    <p>Solo se habilita correctamente cuando todas las líneas fueron guardadas con una cantidad física.</p>
                </div>
                <form
                    method="POST"
                    action="{{ route('inventarios-periodicos.cerrar', $inventarioPeriodico) }}"
                    data-confirm="¿Cerrar este inventario? Las diferencias se convertirán en movimientos de ajuste y ya no podrán editarse."
                    data-confirm-title="Cerrar inventario periódico"
                    data-confirm-label="Cerrar y ajustar"
                >
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="button button--primary" @disabled($lineasContadas < $inventarioPeriodico->total_lineas)>
                        <x-ui.icon name="check-circle" :size="17" /> Cerrar inventario
                    </button>
                </form>
            </div>
        </section>

        <section class="panel action-panel periodic-inventory-cancel-panel periodic-inventory-panel" data-periodic-panel="cancel" @if ($vistaInicial !== 'cancel') hidden @endif>
            <div class="panel-heading">
                <p class="eyebrow">Descartar conteo</p>
                <h2>Anular sin modificar existencias</h2>
                <p>Úsalo cuando hubo movimientos posteriores o cuando el conteo deba empezar nuevamente.</p>
            </div>
            <form
                method="POST"
                action="{{ route('inventarios-periodicos.anular', $inventarioPeriodico) }}"
                class="form-grid"
                data-confirm="¿Anular este conteo? No se aplicará ninguna diferencia al inventario."
                data-confirm-tone="danger"
                data-confirm-label="Anular conteo"
            >
                @csrf
                @method('PATCH')
                <label class="form-field form-grid__full">
                    <span>Motivo de anulación</span>
                    <input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required placeholder="Explica por qué debe repetirse el conteo">
                </label>
                <div class="form-actions form-grid__full">
                    <button type="submit" class="button button--danger">Anular conteo</button>
                </div>
            </form>
        </section>
    @endif

    <section class="panel periodic-inventory-trace periodic-inventory-panel" data-periodic-panel="trace" @if ($vistaInicial !== 'trace') hidden @endif>
        <div class="periodic-inventory-trace__summary">
            <span class="periodic-inventory-trace__heading-icon"><x-ui.icon name="movements" :size="19" /></span>
            <div>
                <h2>Trazabilidad del conteo</h2>
                <p>Responsables y momentos principales de este inventario.</p>
            </div>
            <x-ui.status-badge :tone="$tono">{{ str($inventarioPeriodico->estado)->title() }}</x-ui.status-badge>
        </div>
        <div class="periodic-inventory-trace__body">
            <div class="periodic-inventory-timeline" aria-label="Línea de tiempo del conteo">
                <article class="periodic-inventory-event periodic-inventory-event--complete">
                    <span class="periodic-inventory-event__marker"><x-ui.icon name="check-circle" :size="18" /></span>
                    <div>
                        <span>Apertura</span>
                        <strong>{{ $inventarioPeriodico->abiertoPor?->nombreVisible() ?? 'Usuario no disponible' }}</strong>
                        <time>{{ $inventarioPeriodico->abierto_en?->format('d/m/Y · H:i') ?? 'Fecha no disponible' }}</time>
                    </div>
                </article>

                <span class="periodic-inventory-timeline__connector {{ ! $estaAbierto ? 'periodic-inventory-timeline__connector--complete' : '' }}" aria-hidden="true"></span>

                <article class="periodic-inventory-event {{ ! $estaAbierto ? 'periodic-inventory-event--complete' : 'periodic-inventory-event--pending' }}">
                    <span class="periodic-inventory-event__marker">
                        <x-ui.icon name="{{ ! $estaAbierto ? 'check-circle' : 'clock' }}" :size="18" />
                    </span>
                    <div>
                        <span>{{ $inventarioPeriodico->estado === 'ANULADO' ? 'Anulación' : 'Cierre' }}</span>
                        @if ($estaAbierto)
                            <strong>Pendiente</strong>
                            <time>Disponible al finalizar el conteo</time>
                        @else
                            <strong>{{ $inventarioPeriodico->cerradoPor?->nombreVisible() ?? str($inventarioPeriodico->estado)->title() }}</strong>
                            <time>{{ $inventarioPeriodico->cerrado_en?->format('d/m/Y · H:i') ?? 'Sin fecha registrada' }}</time>
                        @endif
                    </div>
                </article>
            </div>

            <aside class="periodic-inventory-trace-note">
                <span><x-ui.icon name="edit" :size="18" /></span>
                <div>
                    <small>Observación inicial</small>
                    <p>{{ $inventarioPeriodico->observacion ?: 'Sin observación registrada.' }}</p>
                </div>
            </aside>
        </div>
    </section>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/inventario-periodico-pasos.js') }}" defer></script>
@endpush
