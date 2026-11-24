@extends('layouts.app')

@section('title', 'Registro de almacén')
@section('page-kicker', 'Administración')
@section('page-title', 'Registro de almacén')

@section('content')
<div class="registro-almacen" data-registro-almacen>
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

    @php
        $totalResultados = $productos?->total() ?? 0;
        $resumenResultados = $totalResultados.' '.($totalResultados === 1 ? 'coincidencia' : 'coincidencias');
    @endphp

    @if ($producto)
        <details id="registro-consulta" class="panel registro-almacen__disclosure" data-registro-resultados>
            <summary>
                @if ($productos)
                    <span>«{{ $busqueda }}» · {{ $resumenResultados }}@if ($totalResultados > 0) · Página {{ $productos->currentPage() }} de {{ $productos->lastPage() }}@endif</span>
                @endif
                <span class="registro-almacen__toggle-label">
                    <span class="registro-almacen__view-results">{{ $productos ? 'Ver resultados' : 'Buscar otro producto' }}</span>
                    <span class="registro-almacen__hide-results">{{ $productos ? 'Ocultar resultados' : 'Ocultar búsqueda' }}</span>
                    <x-ui.icon name="chevron-down" :size="16" />
                </span>
            </summary>
    @endif
    <section class="panel filter-panel" aria-label="Buscar productos">
        <form class="filter-grid registro-almacen__filters" action="{{ route('registro-almacen.index') }}" method="GET">
            <div class="form-field filter-grid__search">
                <label for="registro-busqueda">Buscar producto o repisa</label>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol" aria-hidden="true"><x-ui.icon name="search" :size="18" /></span>
                    <input id="registro-busqueda" type="search" name="q" maxlength="100" value="{{ $busqueda }}"
                        placeholder="Código, descripción o repisa" aria-describedby="registro-busqueda-ayuda" data-suggest-table=".registro-almacen__results" required>
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
                @unless ($producto)
                    <span class="count-chip">{{ $resumenResultados }}</span>
                @endunless
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
                                        <a href="{{ $urlProducto }}" class="table-primary-link"
                                            aria-label="Ver {{ $resultado->codigo }} · {{ $resultado->descripcion }}"
                                            @if ($seleccionado) aria-current="true" @endif>{{ $resultado->codigo }}</a>
                                    </td>
                                    <td role="cell" data-label="Descripción">{{ $resultado->descripcion }}</td>
                                    <td role="cell" data-label="Unidad de medida">{{ $resultado->unidadMedida?->codigo ?? '—' }}</td>
                                    <td role="cell" data-label="Acción">
                                        @if ($seleccionado)
                                            <x-ui.status-badge tone="info">Seleccionado</x-ui.status-badge>
                                        @else
                                            <a href="{{ $urlProducto }}" class="button button--ghost"
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
                    :action-url="$producto ? $productos->fragment('resultados')->url(1) : $productos->url(1)" action-label="Ir a la primera página" action-icon="search" />
            @endif
            @if ($productos->isNotEmpty())
                <x-ui.pagination :paginator="$producto ? $productos->fragment('resultados') : $productos" />
            @endif
        </section>
    @elseif (!$producto)
        <section class="panel registro-almacen__panel">
            <x-ui.empty-table icon="inventory" title="Consulta el registro de almacén"
                description="Usa el buscador para encontrar un producto o consultar qué hay en una repisa." />
        </section>
    @endif

    @if ($producto)
        </details>
    @endif

    @if ($producto)
        @php
            $existencias = $producto->inventarios->sortBy('repisa.codigo');
            $origenes = $existencias->filter(fn ($fila) => $fila->repisa->estado && (float) $fila->stock_actual > 0);
            $unidad = $producto->unidadMedida?->codigo;
            $tabs = [
                'existencias' => ['Existencias', 'shelf'],
                'traslado' => ['Mover stock', 'movements'],
                'saldo' => ['Registrar saldo inicial', 'plus'],
                'precios' => ['Precios de referencia', 'banknote'],
                'historial' => ['Historial', 'clipboard'],
            ];
            if ($origenes->isEmpty()) {
                unset($tabs['traslado']);
            }

            $tabConErrores = match (true) {
                ! $errors->any() => null,
                old('tipo') === 'TRASLADO' && isset($tabs['traslado']) => 'traslado',
                old('tipo') === 'SALDO_INICIAL' => 'saldo',
                $errors->hasAny(['precio_soles', 'igv_modo', 'fecha_referencia', 'fuente']) => 'precios',
                default => null,
            };
            $tabInicial = $tabConErrores ?? 'existencias';
        @endphp

        <section id="registro-producto" class="panel registro-almacen__panel registro-almacen__product" aria-labelledby="registro-producto-titulo">
            <div class="panel-heading panel-heading--split">
                <div>
                    <p class="eyebrow">Producto seleccionado · {{ $producto->codigo }} @if ($unidad) · {{ $unidad }} @endif</p>
                    <h2 id="registro-producto-titulo">{{ $producto->descripcion }}</h2>
                </div>
                <button type="button" class="button button--ghost" data-registro-cambiar>Cambiar producto</button>
            </div>
            <div class="registro-almacen__indicators">
                <span class="location-chip">Stock total: <x-ui.quantity :value="$existencias->sum('stock_actual')" /> {{ $unidad }}</span>
                <span class="location-chip">{{ $existencias->count() }} {{ $existencias->count() === 1 ? 'ubicación registrada' : 'ubicaciones registradas' }}</span>
            </div>
        </section>

        <div class="product-detail-workspace registro-almacen__workspace" data-product-detail-tabs data-default-tab="existencias"
            @if ($tabConErrores) data-registro-tab-error="{{ $tabConErrores }}" @endif>
            <div class="product-detail-tabs" role="tablist" aria-label="Secciones del producto">
                @foreach ($tabs as $tab => [$etiqueta, $icono])
                    <button type="button" class="product-detail-tab" id="registro-tab-{{ $tab }}" role="tab"
                        data-product-detail-tab="{{ $tab }}" aria-controls="registro-{{ $tab }}"
                        aria-selected="{{ $tab === $tabInicial ? 'true' : 'false' }}" tabindex="{{ $tab === $tabInicial ? 0 : -1 }}">
                        <x-ui.icon :name="$icono" :size="17" />
                        <span>{{ $etiqueta }}</span>
                    </button>
                @endforeach
            </div>

            <section id="registro-existencias" class="panel registro-almacen__panel product-detail-tab-panel" role="tabpanel"
                aria-labelledby="registro-tab-existencias" data-product-detail-panel="existencias" tabindex="0" @if ($tabInicial !== 'existencias') hidden @endif>
                <div class="panel-heading registro-almacen__heading">
                    <h2>Existencias por repisa</h2>
                </div>
                @if ($existencias->isNotEmpty())
                <div class="table-wrap table-wrap--responsive">
                    <table class="data-table data-table--responsive registro-almacen__table" role="table">
                        <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Repisa</th><th scope="col" role="columnheader" class="registro-almacen__numeric">Stock</th><th scope="col" role="columnheader" class="registro-almacen__numeric">Costo promedio</th><th scope="col" role="columnheader" class="registro-almacen__numeric">Mínimo / máximo</th><th scope="col" role="columnheader">Acciones</th></tr></thead>
                        <tbody role="rowgroup">
                            @foreach ($existencias as $fila)
                                <tr role="row">
                                    <td role="cell" data-label="Repisa"><div class="registro-almacen__cell-value">{{ $fila->repisa->codigo }} @unless ($fila->repisa->estado) · Inactiva @endunless</div></td>
                                    <td role="cell" data-label="Stock" class="registro-almacen__numeric"><div class="registro-almacen__cell-value"><x-ui.quantity :value="$fila->stock_actual" /> {{ $unidad }}</div></td>
                                    <td role="cell" data-label="Costo promedio" class="registro-almacen__numeric"><div class="registro-almacen__cell-value"><x-ui.money :value="$fila->costo_promedio_soles" /></div></td>
                                    <td role="cell" data-label="Mínimo / máximo" class="registro-almacen__numeric"><div class="registro-almacen__cell-value"><x-ui.quantity :value="$fila->stock_minimo" /> / {{ $fila->stock_maximo === null ? '—' : '' }}@if ($fila->stock_maximo !== null)<x-ui.quantity :value="$fila->stock_maximo" />@endif</div></td>
                                    <td role="cell" data-label="Acciones"><div class="registro-almacen__cell-value"><a href="{{ route('inventario.edit', $fila->id) }}">Editar límites</a></div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                    <div class="registro-almacen__empty">
                        <p>Todavía no hay existencias registradas.</p>
                        <button type="button" class="button button--ghost" data-registro-abrir-tab="saldo">Registrar saldo inicial</button>
                    </div>
                @endif
            </section>

            @if ($origenes->isNotEmpty())
                @include('registro_almacen._operacion', ['tipo' => 'TRASLADO', 'titulo' => 'Mover stock', 'tabOperacion' => 'traslado'])
            @endif
            @include('registro_almacen._operacion', ['tipo' => 'SALDO_INICIAL', 'titulo' => 'Registrar saldo inicial', 'tabOperacion' => 'saldo'])

            <section id="registro-precios" class="panel form-panel registro-almacen__panel product-detail-tab-panel" role="tabpanel"
                aria-labelledby="registro-tab-precios" data-product-detail-panel="precios" tabindex="0" @if ($tabInicial !== 'precios') hidden @endif>
                <div class="panel-heading">
                    <h2 id="registro-precios-titulo">Precios de referencia</h2>
                    <p>Se guarda como referencia del producto para consulta. El Kardex conserva su costo promedio.</p>
                </div>
                <form class="stack-form" method="POST" action="{{ route('registro-almacen.precios.store', $producto) }}"
                    data-dirty-form @if ($tabConErrores === 'precios') data-registro-draft-restored @endif>
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

            <section id="registro-historial" class="panel registro-almacen__panel product-detail-tab-panel" role="tabpanel"
                aria-labelledby="registro-tab-historial" data-product-detail-panel="historial" tabindex="0" @if ($tabInicial !== 'historial') hidden @endif>
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
                    <div class="registro-almacen__empty"><p>Aún no hay operaciones para este producto.</p></div>
                @endif
            </section>
        </div>

        <form hidden data-registro-confirmar-salida
            data-confirm="Se perderán los cambios sin guardar al continuar. Puedes cancelar para seguir editando."
            data-confirm-title="¿Descartar cambios?"
            data-confirm-label="Descartar cambios"
            data-confirm-tone="danger"></form>
    @endif
</div>
@endsection

@push('scripts')
    @if ($producto)
        <script src="{{ asset('js/registro-almacen.js') }}" defer></script>
        <script src="{{ asset('js/product-detail-tabs.js') }}" defer></script>
    @endif
@endpush
