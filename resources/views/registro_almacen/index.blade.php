@extends('layouts.app')

@section('title', 'Registro de almacén')
@section('page-kicker', 'Administración')
@section('page-title', 'Registro de almacén')

@section('content')
    <x-ui.page-header
        kicker="Administración"
        title="Registro de almacén"
        description="Consulta la ubicación de un producto, registra traslados y guarda precios de referencia."
    >
        @if (auth()->user()->puede('salidas.registrar'))
            <x-slot:actions>
                <a class="button button--ghost" href="{{ route('notas-salida.create', ['motivo_salida' => 'USO_INTERNO']) }}">Entrega a personal</a>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <section class="panel form-panel">
        <form class="form-grid form-grid--two" action="{{ route('registro-almacen.index') }}" method="GET">
            <label class="form-field">
                <span>Buscar producto o repisa</span>
                <input type="search" name="q" maxlength="100" value="{{ $busqueda }}" placeholder="Código, descripción o repisa" required>
            </label>
            <div class="form-field">
                <button type="submit" class="button button--primary">Buscar</button>
            </div>
        </form>
        @if ($productos)
            <ul>
                @forelse ($productos as $resultado)
                    <li>
                        <a href="{{ route('registro-almacen.index', ['q' => $busqueda, 'producto' => $resultado->id, 'page' => $productos->currentPage()]) }}">
                            <strong>{{ $resultado->codigo }}</strong> · {{ $resultado->descripcion }}
                        </a>
                    </li>
                @empty
                    <li>No se encontraron productos activos.</li>
                @endforelse
            </ul>
            {{ $productos->links() }}
        @endif
    </section>

    @if ($producto)
        @php
            $existencias = $producto->inventarios->sortBy('repisa.codigo');
            $origenes = $existencias->filter(fn ($fila) => $fila->repisa->estado && (float) $fila->stock_actual > 0);
            $unidad = $producto->unidadMedida?->codigo;
        @endphp

        <section class="panel form-panel">
            <div class="panel-heading">
                <p class="eyebrow">{{ $producto->codigo }} · {{ $unidad }}</p>
                <h2>{{ $producto->descripcion }}</h2>
                <p>Total en almacén: <x-ui.quantity :value="$existencias->sum('stock_actual')" /> {{ $unidad }}</p>
            </div>
            <div class="table-wrap table-wrap--responsive">
                <table class="data-table data-table--responsive">
                    <thead><tr><th>Repisa</th><th>Stock</th><th>Costo promedio</th><th>Mínimo / máximo</th><th>Acciones</th></tr></thead>
                    <tbody>
                        @forelse ($existencias as $fila)
                            <tr>
                                <td data-label="Repisa">{{ $fila->repisa->codigo }} @unless ($fila->repisa->estado) · Inactiva @endunless</td>
                                <td data-label="Stock"><x-ui.quantity :value="$fila->stock_actual" /> {{ $unidad }}</td>
                                <td data-label="Costo promedio"><x-ui.money :value="$fila->costo_promedio_soles" /></td>
                                <td data-label="Mínimo / máximo"><x-ui.quantity :value="$fila->stock_minimo" /> / {{ $fila->stock_maximo === null ? '—' : '' }}@if ($fila->stock_maximo !== null)<x-ui.quantity :value="$fila->stock_maximo" />@endif</td>
                                <td data-label="Acciones"><a href="{{ route('inventario.edit', $fila->id) }}">Editar límites</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5">Todavía no hay existencias registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($origenes->isNotEmpty())
            @include('registro_almacen._operacion', ['tipo' => 'TRASLADO', 'titulo' => 'Mover stock'])
        @endif
        @include('registro_almacen._operacion', ['tipo' => 'SALDO_INICIAL', 'titulo' => 'Registrar saldo inicial'])

        <section class="panel form-panel">
            <div class="panel-heading">
                <h2>Precio de referencia</h2>
                <p>Se guarda como referencia del producto para consulta. El Kardex conserva su costo promedio.</p>
            </div>
            <form class="stack-form" method="POST" action="{{ route('registro-almacen.precios.store', $producto) }}" data-dirty-form>
                @csrf
                <div class="form-grid form-grid--two">
                    <label class="form-field">
                        <span>Precio unitario en soles</span>
                        <input name="precio_soles" type="number" inputmode="decimal" min="0.0001" max="9999999999.9999" step="0.0001" value="{{ old('precio_soles') }}" required>
                        @error('precio_soles')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field">
                        <span>Tratamiento del IGV</span>
                        <select name="igv_modo" required>
                            <option value="" @selected(!old('igv_modo'))>Selecciona según el precio de origen</option>
                            @foreach ($modosIgv as $codigo => $modo)
                                <option value="{{ $codigo }}" @selected(old('igv_modo') === $codigo)>{{ $modo }}</option>
                            @endforeach
                        </select>
                        @error('igv_modo')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field">
                        <span>Fecha de referencia</span>
                        <input name="fecha_referencia" type="date" value="{{ old('fecha_referencia', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                        @error('fecha_referencia')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field">
                        <span>Fuente</span>
                        <input name="fuente" type="text" maxlength="150" placeholder="Ej. Lista de proveedor o Excel de almacén" value="{{ old('fuente') }}" required>
                        @error('fuente')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                </div>
                <div class="form-actions"><button type="submit" class="button button--primary">Guardar referencia</button></div>
            </form>
            @if ($precios->isNotEmpty() || $referenciasImportadas->isNotEmpty())
                <h3>Referencias recientes</h3>
                <ul>
                    @foreach ($precios as $precio)
                        <li>{{ \Illuminate\Support\Carbon::parse($precio->fecha_referencia)->format('d/m/Y') }} · S/ {{ $precio->precio_soles }} · {{ $modosIgv[$precio->igv_modo] }} · {{ $precio->fuente }}</li>
                    @endforeach
                    @foreach ($referenciasImportadas as $precio)
                        <li>S/ {{ $precio->costo_unitario_pen }} · Importado de {{ $precio->origen }} (fila {{ $precio->fila_excel }})</li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($operaciones->isNotEmpty())
            <section class="panel form-panel">
                <div class="panel-heading"><h2>Operaciones recientes</h2></div>
                <div class="table-wrap table-wrap--responsive">
                    <table class="data-table data-table--responsive">
                        <thead><tr><th>Operación</th><th>Ubicaciones</th><th>Cantidad</th><th>Motivo</th><th>Registrado por</th></tr></thead>
                        <tbody>
                            @foreach ($operaciones as $operacion)
                                <tr>
                                    <td data-label="Operación">
                                        @if (auth()->user()->puede('movimientos.ver'))
                                            <a href="{{ route('movimientos.index', ['origen_tipo' => 'REGISTRO_ALMACEN', 'origen_id' => $operacion->id]) }}">#{{ $operacion->id }}</a>
                                        @else
                                            #{{ $operacion->id }}
                                        @endif
                                        · {{ $operacion->tipo === 'TRASLADO' ? 'Traslado' : 'Saldo inicial' }}
                                    </td>
                                    <td data-label="Ubicaciones">@if ($operacion->origen_codigo){{ $operacion->origen_codigo }} → @endif{{ $operacion->destino_codigo }}</td>
                                    <td data-label="Cantidad"><x-ui.quantity :value="$operacion->cantidad" /> {{ $unidad }}</td>
                                    <td data-label="Motivo">{{ $operacion->observacion }}</td>
                                    <td data-label="Registrado por">{{ $operacion->username }} · {{ \Illuminate\Support\Carbon::parse($operacion->created_at)->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
@endsection
