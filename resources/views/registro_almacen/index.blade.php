@extends('layouts.app')

@section('title', 'Registro de almacén')
@section('page-kicker', 'Administración')
@section('page-title', 'Registro de almacén')

@section('content')
<div class="registro-almacen">
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

    <section class="panel filter-panel" aria-label="Buscar productos">
        <form class="filter-grid registro-almacen__filters" action="{{ route('registro-almacen.index') }}" method="GET">
            <div class="form-field filter-grid__search">
                <label for="registro-busqueda">Buscar producto o repisa</label>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol" aria-hidden="true"><x-ui.icon name="search" :size="18" /></span>
                    <input id="registro-busqueda" type="search" name="q" maxlength="100" value="{{ $busqueda }}"
                        placeholder="Código, descripción o repisa" aria-describedby="registro-busqueda-ayuda" required>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="button button--primary">Buscar</button>
                @if ($producto)
                    <button type="submit" form="registro-limpiar" class="button button--ghost">Limpiar</button>
                @else
                    <a href="{{ route('registro-almacen.index') }}" class="button button--ghost">Limpiar</a>
                @endif
            </div>
        </form>
        @if ($producto)
            <form id="registro-limpiar" method="GET" action="{{ route('registro-almacen.index') }}"
                data-confirm="Se cerrará el producto seleccionado y se perderán los cambios que todavía no hayas guardado."
                data-confirm-title="Limpiar búsqueda"
                data-confirm-label="Limpiar y salir"></form>
        @endif
        <p id="registro-busqueda-ayuda" class="registro-almacen__help">Escribe un código de producto, parte de su descripción o un código de repisa.</p>
    </section>

    @if ($productos)
        <section id="registro-resultados" class="panel registro-almacen__panel" aria-labelledby="registro-resultados-titulo">
            <div class="panel-heading panel-heading--split registro-almacen__heading">
                <h2 id="registro-resultados-titulo">Resultados de búsqueda</h2>
                <span class="count-chip">{{ $productos->total() }} {{ $productos->total() === 1 ? 'coincidencia' : 'coincidencias' }}</span>
            </div>
            @if ($productos->isNotEmpty())
                <div class="table-wrap table-wrap--responsive" data-responsive-table>
                    <table class="data-table data-table--responsive registro-almacen__table registro-almacen__results" role="table">
                        <thead role="rowgroup">
                            <tr role="row">
                                <th scope="col" role="columnheader">Código</th>
                                <th scope="col" role="columnheader">Descripción</th>
                                <th scope="col" role="columnheader">Unidad</th>
                                <th scope="col" role="columnheader">Acción</th>
                            </tr>
                        </thead>
                        <tbody role="rowgroup">
                            @foreach ($productos as $resultado)
                                @php
                                    $seleccionado = $producto?->is($resultado) ?? false;
                                    $urlProducto = route('registro-almacen.index', ['q' => $busqueda, 'producto' => $resultado->id, 'page' => $productos->currentPage()]);
                                @endphp
                                <tr role="row" @class(['registro-almacen__selected-row' => $seleccionado])>
                                    <td role="cell" data-label="Código">
                                        <a href="{{ $urlProducto }}#registro-producto" class="table-primary-link"
                                            aria-label="Ver {{ $resultado->codigo }} · {{ $resultado->descripcion }}"
                                            @if ($seleccionado) aria-current="true" @endif>{{ $resultado->codigo }}</a>
                                    </td>
                                    <td role="cell" data-label="Descripción">{{ $resultado->descripcion }}</td>
                                    <td role="cell" data-label="Unidad de medida">{{ $resultado->unidadMedida?->codigo ?? '—' }}</td>
                                    <td role="cell" data-label="Acción">
                                        @if ($seleccionado)
                                            <x-ui.status-badge tone="info">Seleccionado</x-ui.status-badge>
                                        @else
                                            <a href="{{ $urlProducto }}#registro-producto" class="button button--ghost"
                                                aria-label="Seleccionar {{ $resultado->codigo }} · {{ $resultado->descripcion }}">Seleccionar</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($productos->total() === 0)
                <x-ui.empty-table icon="search" title="No hay coincidencias"
                    description="Prueba con otro código, descripción o repisa."
                    action-url="#registro-busqueda" action-label="Volver a buscar" action-icon="search" />
            @else
                <x-ui.empty-table icon="products" title="No hay productos en esta página"
                    description="Vuelve a la primera página para consultar las coincidencias."
                    :action-url="$productos->url(1)" action-label="Ir a la primera página" action-icon="search" />
            @endif
            @if ($productos->isNotEmpty())
                <x-ui.pagination :paginator="$productos" />
            @endif
        </section>
    @elseif (!$producto)
        <section class="panel registro-almacen__panel">
            <x-ui.empty-table icon="inventory" title="Consulta el registro de almacén"
                description="Usa el buscador para encontrar un producto o consultar qué hay en una repisa." />
        </section>
    @endif

    @if ($producto)
        @php
            $existencias = $producto->inventarios->sortBy('repisa.codigo');
            $origenes = $existencias->filter(fn ($fila) => $fila->repisa->estado && (float) $fila->stock_actual > 0);
            $unidad = $producto->unidadMedida?->codigo;
        @endphp

        <section id="registro-producto" tabindex="-1" class="panel registro-almacen__panel registro-almacen__product" aria-labelledby="registro-producto-titulo">
            <div class="panel-heading">
                <p class="eyebrow">Producto seleccionado · {{ $producto->codigo }} @if ($unidad) · {{ $unidad }} @endif</p>
                <h2 id="registro-producto-titulo">{{ $producto->descripcion }}</h2>
            </div>
            <nav class="registro-almacen__sections" aria-label="Secciones del producto">
                <a href="#registro-existencias" class="button button--ghost">Existencias</a>
                @if ($origenes->isNotEmpty())
                    <a href="#registro-traslado" class="button button--ghost">Mover stock</a>
                @endif
                <a href="#registro-saldo" class="button button--ghost">Registrar saldo inicial</a>
                <a href="#registro-precios" class="button button--ghost">Precios de referencia</a>
                <a href="#registro-historial" class="button button--ghost">Historial</a>
            </nav>
        </section>

        <section id="registro-existencias" class="panel registro-almacen__panel" aria-labelledby="registro-existencias-titulo">
            <div class="panel-heading panel-heading--split registro-almacen__heading">
                <h2 id="registro-existencias-titulo">Existencias por repisa</h2>
                <span class="location-chip">Total: <x-ui.quantity :value="$existencias->sum('stock_actual')" /> {{ $unidad }}</span>
            </div>
            <div class="table-wrap table-wrap--responsive">
                <table class="data-table data-table--responsive registro-almacen__table" role="table">
                    <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Repisa</th><th scope="col" role="columnheader" class="registro-almacen__numeric">Stock</th><th scope="col" role="columnheader" class="registro-almacen__numeric">Costo promedio</th><th scope="col" role="columnheader" class="registro-almacen__numeric">Mínimo / máximo</th><th scope="col" role="columnheader">Acciones</th></tr></thead>
                    <tbody role="rowgroup">
                        @forelse ($existencias as $fila)
                            <tr role="row">
                                <td role="cell" data-label="Repisa"><div class="registro-almacen__cell-value">{{ $fila->repisa->codigo }} @unless ($fila->repisa->estado) · Inactiva @endunless</div></td>
                                <td role="cell" data-label="Stock" class="registro-almacen__numeric"><div class="registro-almacen__cell-value"><x-ui.quantity :value="$fila->stock_actual" /> {{ $unidad }}</div></td>
                                <td role="cell" data-label="Costo promedio" class="registro-almacen__numeric"><div class="registro-almacen__cell-value"><x-ui.money :value="$fila->costo_promedio_soles" /></div></td>
                                <td role="cell" data-label="Mínimo / máximo" class="registro-almacen__numeric"><div class="registro-almacen__cell-value"><x-ui.quantity :value="$fila->stock_minimo" /> / {{ $fila->stock_maximo === null ? '—' : '' }}@if ($fila->stock_maximo !== null)<x-ui.quantity :value="$fila->stock_maximo" />@endif</div></td>
                                <td role="cell" data-label="Acciones"><div class="registro-almacen__cell-value"><a href="{{ route('inventario.edit', $fila->id) }}">Editar límites</a></div></td>
                            </tr>
                        @empty
                            <tr role="row"><td role="cell" colspan="5">Todavía no hay existencias registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($origenes->isNotEmpty())
            @include('registro_almacen._operacion', ['tipo' => 'TRASLADO', 'titulo' => 'Mover stock', 'seccionId' => 'registro-traslado'])
        @endif
        @include('registro_almacen._operacion', ['tipo' => 'SALDO_INICIAL', 'titulo' => 'Registrar saldo inicial', 'seccionId' => 'registro-saldo'])

        <section id="registro-precios" class="panel form-panel registro-almacen__panel" aria-labelledby="registro-precios-titulo">
            <div class="panel-heading">
                <h2 id="registro-precios-titulo">Precios de referencia</h2>
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

        <section id="registro-historial" class="panel registro-almacen__panel" aria-labelledby="registro-historial-titulo">
            <div class="panel-heading registro-almacen__heading"><h2 id="registro-historial-titulo">Historial de operaciones</h2></div>
            @if ($operaciones->isNotEmpty())
                <div class="table-wrap table-wrap--responsive">
                    <table class="data-table data-table--responsive registro-almacen__table" role="table">
                        <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Operación</th><th scope="col" role="columnheader">Ubicaciones</th><th scope="col" role="columnheader" class="registro-almacen__numeric">Cantidad</th><th scope="col" role="columnheader">Motivo</th><th scope="col" role="columnheader">Registrado por</th></tr></thead>
                        <tbody role="rowgroup">
                            @foreach ($operaciones as $operacion)
                                <tr role="row">
                                    <td role="cell" data-label="Operación">
                                        <div class="registro-almacen__cell-value">
                                        @if (auth()->user()->puede('movimientos.ver'))
                                            <a href="{{ route('movimientos.index', ['origen_tipo' => 'REGISTRO_ALMACEN', 'origen_id' => $operacion->id]) }}">#{{ $operacion->id }}</a>
                                        @else
                                            #{{ $operacion->id }}
                                        @endif
                                        · {{ $operacion->tipo === 'TRASLADO' ? 'Traslado' : 'Saldo inicial' }}
                                        </div>
                                    </td>
                                    <td role="cell" data-label="Ubicaciones"><div class="registro-almacen__cell-value">@if ($operacion->origen_codigo){{ $operacion->origen_codigo }} → @endif{{ $operacion->destino_codigo }}</div></td>
                                    <td role="cell" data-label="Cantidad" class="registro-almacen__numeric"><div class="registro-almacen__cell-value"><x-ui.quantity :value="$operacion->cantidad" /> {{ $unidad }}</div></td>
                                    <td role="cell" data-label="Motivo"><div class="registro-almacen__cell-value">{{ $operacion->observacion }}</div></td>
                                    <td role="cell" data-label="Registrado por"><div class="registro-almacen__cell-value">{{ $operacion->username }} · {{ \Illuminate\Support\Carbon::parse($operacion->created_at)->format('d/m/Y H:i') }}</div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-ui.empty-table icon="clipboard" title="Aún no hay operaciones"
                    description="Aquí aparecerán los traslados y saldos iniciales de este producto." />
            @endif
        </section>
    @endif
</div>
@endsection
