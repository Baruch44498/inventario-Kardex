@extends('layouts.app')

@section('title', 'Componentes '.$cotizacion->codigo)
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Componentes de la cotización')

@section('content')
    <a href="{{ route('cotizaciones-cliente.show', $cotizacion) }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a {{ $cotizacion->codigo }}
    </a>

    <section class="supplier-quote-hero commercial-document-hero">
        <div><p class="eyebrow">{{ $cotizacion->codigo }} · Compatibilidad</p><h1>Contexto de la orden principal</h1><p>Los componentes existentes se conservan como referencia, pero todos se consolidarán en una sola orden principal.</p></div>
        <x-ui.status-badge :tone="$cotizacion->tonoEstadoVisual()" class="badge--large">{{ $cotizacion->estadoVisual() }}</x-ui.status-badge>
    </section>

    <section class="notice notice--info notice--block">
        <x-ui.icon name="orders" :size="20" />
        <div><strong>Transición a planificación por áreas</strong><span>Para nuevos trabajos, organiza los materiales desde la hoja de costos usando áreas. Un componente adicional ya no crea otra orden.</span></div>
    </section>

    @if ($errors->any())
        <section class="notice notice--danger notice--block">
            <x-ui.icon name="error" :size="20" />
            <div>
                <strong>Revisa la configuración</strong>
                <span>{{ $errors->first() }}</span>
            </div>
        </section>
    @endif

    @foreach ($cotizacion->componentes as $componente)
        <section class="panel quote-component-card">
            <header class="supplier-panel-heading">
                <div><p class="eyebrow">Componente {{ $componente->orden_secuencia }}</p><h2>{{ $componente->tipoOrden?->codigo }} · {{ $componente->descripcion_componente }}</h2></div>
            </header>
            @if ($cotizacion->esEditable())
                <div class="quote-component-cost-action">
                    <div>
                        <span class="quote-component-cost-action__step">Siguiente paso</span>
                        <strong>Cargar la hoja de costos de este trabajo</strong>
                        <small>Materiales, mano de obra, terceros, transporte, viáticos y consumibles.</small>
                    </div>
                    <a class="button button--primary button--large" href="{{ route('cotizaciones-cliente.presupuesto.show', ['cotizacionCliente' => $cotizacion, 'componente_id' => $componente->id]) }}">
                        <x-ui.icon name="quotes" :size="18" />
                        Cargar costos
                    </a>
                </div>
            @endif
            @if ($cotizacion->esEditable() && ! $componente->orden_operacion_id)
                <form method="POST" action="{{ route('cotizacion-componentes.update', $componente) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="formulario" value="editar_componente_{{ $componente->id }}">
                    <input type="hidden" name="tipo_orden_id" value="{{ $componente->tipo_orden_id }}">
                    <div class="operation-form-grid">
                        <div class="form-field">
                            <span>Tipo de orden definido</span>
                            <div class="quote-component-fixed-type" data-component-fixed-type="{{ $componente->tipoOrden?->codigo }}">
                                <span class="type-chip">{{ $componente->tipoOrden?->codigo }}</span>
                                <strong>{{ $componente->tipoOrden?->nombre }}</strong>
                                <small>No puede modificarse después de crear el componente.</small>
                            </div>
                        </div>
                        <label class="form-field form-field--span-2"><span>Descripción</span><input name="descripcion_componente" value="{{ $componente->descripcion_componente }}" minlength="5" maxlength="500" required></label>
                        <label class="form-field"><span>Ubicación</span><select name="cliente_direccion_id"><option value="">Sin ubicación</option>@foreach ($direcciones as $direccion)<option value="{{ $direccion->id }}" @selected($componente->cliente_direccion_id === $direccion->id)>{{ $direccion->destino ?: $direccion->direccion }}</option>@endforeach</select></label>
                        <label class="form-field"><span>Vehículo</span><select name="vehiculo_id"><option value="">Sin vehículo</option>@foreach ($vehiculos as $vehiculo)<option value="{{ $vehiculo->id }}" @selected($componente->vehiculo_id === $vehiculo->id)>{{ $vehiculo->identificadorVisible() }}</option>@endforeach</select></label>
                        <label class="form-field"><span>TC comparativo PEN/USD</span><input type="number" name="tipo_cambio_comparacion" min="0.1" max="100" step="0.000001" value="{{ $componente->tipo_cambio_comparacion }}" placeholder="Recomendado para OP"></label>
                    </div>
                    <div class="form-actions"><button class="button button--primary" type="submit">Guardar componente</button></div>
                </form>
                @if ($cotizacion->componentes->count() > 1)
                    <form method="POST" action="{{ route('cotizacion-componentes.destroy', $componente) }}" data-confirm="¿Eliminar este componente vacío?">@csrf @method('DELETE')<button type="submit" class="button button--danger">Eliminar componente</button></form>
                @endif
            @elseif ($componente->ordenOperacion)
                <a class="button button--secondary" href="{{ route('ordenes-operacion.show', $componente->ordenOperacion) }}">Ver {{ $componente->ordenOperacion->codigo_orden }}</a>
            @endif
        </section>
    @endforeach

    @if ($cotizacion->esEditable())
        @if ($cotizacion->detalles->isNotEmpty() || $cotizacion->presupuestos->isNotEmpty())
        <section class="panel supplier-quote-detail-lines">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Referencia anterior</p><h2>Asignaciones históricas</h2><p>Estas asignaciones se conservan, pero la conversión consolidará todos los materiales en la orden principal.</p></div></header>
            <form method="POST" action="{{ route('cotizaciones-cliente.componentes.asignar', $cotizacion) }}">
                @csrf @method('PUT')
                <div class="table-wrap"><table class="data-table"><thead><tr><th>Línea</th><th>Clase</th><th>Componente</th></tr></thead><tbody>
                    @foreach ($cotizacion->detalles as $detalle)
                        <tr>
                            <td><strong>{{ $detalle->codigo_producto }}</strong><span>{{ $detalle->descripcion }}</span></td>
                            <td>Producto comercial</td>
                            <td>
                                <input
                                    type="hidden"
                                    name="detalles[{{ $loop->index }}][detalle_id]"
                                    value="{{ $detalle->id }}"
                                >
                                <select name="detalles[{{ $loop->index }}][componente_id]" required>
                                    <option value="">Selecciona</option>
                                    @foreach ($cotizacion->componentes as $componente)
                                        <option value="{{ $componente->id }}" @selected($detalle->componente_id === $componente->id)>
                                            {{ $componente->tipoOrden?->codigo }} {{ $componente->orden_secuencia }} · {{ $componente->descripcion_componente }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                    @foreach ($cotizacion->presupuestos as $partida)
                        <tr>
                            <td><strong>{{ $partida->tipoVisible() }}</strong><span>{{ $partida->descripcion }}</span></td>
                            <td>Costo estimado</td>
                            <td>
                                <input
                                    type="hidden"
                                    name="presupuestos[{{ $loop->index }}][presupuesto_id]"
                                    value="{{ $partida->id }}"
                                >
                                <select name="presupuestos[{{ $loop->index }}][componente_id]" required>
                                    <option value="">Selecciona</option>
                                    @foreach ($cotizacion->componentes as $componente)
                                        <option value="{{ $componente->id }}" @selected($partida->componente_id === $componente->id)>
                                            {{ $componente->tipoOrden?->codigo }} {{ $componente->orden_secuencia }} · {{ $componente->descripcion_componente }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody></table></div>
                <div class="form-actions"><button class="button button--primary" type="submit">Guardar asignaciones</button></div>
            </form>
        </section>
        @else
            <section class="notice notice--success notice--block">
                <x-ui.icon name="quotes" :size="20" />
                <div>
                    <strong>Paso 3 de 3 · Carga la hoja de costos</strong>
                    <span>Usa “Cargar costos de este trabajo” en cada componente. Al terminar, sincroniza el costeo para generar automáticamente las líneas comerciales del cliente.</span>
                </div>
            </section>
        @endif
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/cotizacion-componentes.js') }}"></script>
@endpush
