@extends('layouts.app')

@section('title', 'Componentes '.$cotizacion->codigo)
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Componentes de la cotización')

@section('content')
    <a href="{{ route('cotizaciones-cliente.show', $cotizacion) }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a {{ $cotizacion->codigo }}
    </a>

    <section class="supplier-quote-hero commercial-document-hero">
        <div><p class="eyebrow">{{ $cotizacion->codigo }} · Uso interno</p><h1>Trabajos y órdenes resultantes</h1><p>Cada componente generará su propia OM, OS u OP al aprobar.</p></div>
        <x-ui.status-badge :tone="$cotizacion->tonoEstadoVisual()" class="badge--large">{{ $cotizacion->estadoVisual() }}</x-ui.status-badge>
    </section>

    <section class="notice notice--info notice--block">
        <x-ui.icon name="orders" :size="20" />
        <div><strong>Una cotización, varios trabajos</strong><span>La venta sigue siendo un solo documento comercial. Productos, costos estimados, vehículo y orden quedan separados por componente.</span></div>
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
        <section class="panel">
            <header class="panel-heading"><p class="eyebrow">Componente {{ $componente->orden_secuencia }}</p><h2>{{ $componente->tipoOrden?->codigo }} · {{ $componente->descripcion_componente }}</h2></header>
            @if ($cotizacion->esEditable() && ! $componente->orden_operacion_id)
                <form method="POST" action="{{ route('cotizacion-componentes.update', $componente) }}">
                    @csrf @method('PUT')
                    <div class="operation-form-grid">
                        <label class="form-field"><span>Tipo</span><select name="tipo_orden_id" required>@foreach ($tipos as $tipo)<option value="{{ $tipo->id }}" @selected($componente->tipo_orden_id === $tipo->id)>{{ $tipo->codigo }} · {{ $tipo->nombre }}</option>@endforeach</select></label>
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
        <section class="panel">
            <header class="panel-heading"><p class="eyebrow">Nuevo trabajo</p><h2>Agregar componente</h2></header>
            <form method="POST" action="{{ route('cotizaciones-cliente.componentes.store', $cotizacion) }}">
                @csrf
                <div class="operation-form-grid">
                    <label class="form-field"><span>Tipo</span><select name="tipo_orden_id" required><option value="">Selecciona</option>@foreach ($tipos as $tipo)<option value="{{ $tipo->id }}">{{ $tipo->codigo }} · {{ $tipo->nombre }}</option>@endforeach</select></label>
                    <label class="form-field form-field--span-2"><span>Descripción</span><input name="descripcion_componente" minlength="5" maxlength="500" required></label>
                    <label class="form-field"><span>Ubicación</span><select name="cliente_direccion_id"><option value="">Sin ubicación</option>@foreach ($direcciones as $direccion)<option value="{{ $direccion->id }}">{{ $direccion->destino ?: $direccion->direccion }}</option>@endforeach</select></label>
                    <label class="form-field"><span>Vehículo</span><select name="vehiculo_id"><option value="">Sin vehículo</option>@foreach ($vehiculos as $vehiculo)<option value="{{ $vehiculo->id }}">{{ $vehiculo->identificadorVisible() }}</option>@endforeach</select></label>
                    <label class="form-field"><span>TC comparativo PEN/USD</span><input type="number" name="tipo_cambio_comparacion" min="0.1" max="100" step="0.000001" placeholder="Recomendado para OP"></label>
                </div>
                <div class="form-actions"><button class="button button--primary" type="submit"><x-ui.icon name="plus" :size="17" /> Agregar componente</button></div>
            </form>
        </section>

        <section class="panel supplier-quote-detail-lines">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Distribución</p><h2>Asignar productos y presupuesto</h2><p>Ninguna línea puede quedar sin componente antes de aprobar.</p></div></header>
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
    @endif
@endsection
