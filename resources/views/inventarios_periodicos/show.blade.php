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
    @endphp

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

    @if ($estaAbierto && $puedeGestionar)
        <form method="POST" action="{{ route('inventarios-periodicos.conteo', $inventarioPeriodico) }}" data-dirty-form>
            @csrf
            @method('PATCH')
    @endif

    <section class="panel">
        <div class="panel-heading panel-heading--split">
            <div>
                <p class="eyebrow">Productos de la repisa</p>
                <h2>{{ $estaAbierto ? 'Registrar conteo físico' : 'Resultado del conteo' }}</h2>
                <p>El costo mostrado es la fotografía contable tomada al abrir el inventario.</p>
            </div>
            <span class="count-chip">{{ $inventarioPeriodico->detalles->count() }}</span>
        </div>

        <div class="table-wrap table-wrap--wide table-wrap--responsive" data-responsive-table>
            <table class="data-table data-table--responsive data-table--periodic-inventory">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Repisa</th>
                        <th class="text-right">Stock sistema</th>
                        <th class="text-right">Conteo físico</th>
                        <th class="text-right">Diferencia</th>
                        <th class="text-right">Costo promedio</th>
                        <th class="text-right">Valor diferencia</th>
                        <th>Observación</th>
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
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $detalle->producto?->codigo }}</strong>
                                <span>{{ $detalle->producto?->descripcion }}</span>
                            </td>
                            <td><span class="location-chip"><x-ui.icon name="shelf" :size="14" />{{ $inventarioPeriodico->repisa?->codigo }}</span></td>
                            <td class="text-right">
                                <strong><x-ui.quantity :value="$detalle->stock_sistema" /></strong>
                                <span>{{ $detalle->producto?->unidadMedida?->codigo }}</span>
                            </td>
                            <td class="text-right">
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
                            <td class="text-right">
                                <strong><x-ui.quantity :value="$detalle->diferencia" /></strong>
                            </td>
                            <td class="text-right"><x-ui.money :value="$detalle->costo_promedio_soles" /></td>
                            <td class="text-right"><x-ui.money :value="$detalle->valor_diferencia_soles" /></td>
                            <td>
                                @if ($estaAbierto && $puedeGestionar)
                                    <input
                                        type="text"
                                        name="detalles[{{ $detalle->id }}][observacion]"
                                        value="{{ $observacionAnterior }}"
                                        maxlength="300"
                                        placeholder="Opcional"
                                        aria-label="Observación de {{ $detalle->producto?->codigo }}"
                                    >
                                @else
                                    {{ $detalle->observacion ?: '—' }}
                                @endif
                            </td>
                        </tr>
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

        <section class="panel action-panel">
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

        <section class="panel action-panel">
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

    <section class="panel detail-panel">
        <div class="panel-heading"><h2>Trazabilidad</h2></div>
        <dl class="detail-grid">
            <div><dt>Abierto por</dt><dd>{{ $inventarioPeriodico->abiertoPor?->nombreVisible() ?? '—' }}</dd></div>
            <div><dt>Fecha de apertura</dt><dd>{{ $inventarioPeriodico->abierto_en?->format('d/m/Y H:i') }}</dd></div>
            <div><dt>Cerrado por</dt><dd>{{ $inventarioPeriodico->cerradoPor?->nombreVisible() ?? '—' }}</dd></div>
            <div><dt>Fecha de cierre</dt><dd>{{ $inventarioPeriodico->cerrado_en?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            <div><dt>Observación inicial</dt><dd>{{ $inventarioPeriodico->observacion ?: '—' }}</dd></div>
        </dl>
    </section>
@endsection
