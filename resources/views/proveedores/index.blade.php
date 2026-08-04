@extends('layouts.app')

@section('title', 'Proveedores')
@section('page-kicker', 'Comercial y logística')
@section('page-title', 'Proveedores')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Abastecimiento</p>
            <h1>Proveedores</h1>
            <p>Administra proveedores y consulta los precios ofrecidos por producto.</p>
        </div>

        <div class="module-header__actions">
            <a href="{{ route('historial-precios.index') }}" class="button button--ghost">
                <x-ui.icon name="banknote" :size="17" /> Historial de precios
            </a>
            <a href="{{ route('cotizaciones-proveedor.create') }}" class="button button--ghost">
                <x-ui.icon name="quotes" :size="17" /> Nueva cotización
            </a>
            <a href="{{ route('proveedores.create') }}" class="button button--primary">
                <x-ui.icon name="plus" :size="18" /> Nuevo proveedor
            </a>
        </div>
    </section>

    <section class="summary-strip summary-strip--four">
        @foreach ([
            ['Total', 'suppliers', 'neutral', $resumen['total']],
            ['Activos', 'check-circle', 'success', $resumen['activos']],
            ['Con cotizaciones', 'quotes', 'info', $resumen['con_cotizaciones']],
            ['Productos cotizados', 'products', 'warning', $resumen['productos_cotizados']],
        ] as [$titulo, $icono, $tono, $valor])
            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--{{ $tono }}">
                    <x-ui.icon :name="$icono" :size="21" />
                </span>
                <div><span>{{ $titulo }}</span><strong>{{ $valor }}</strong></div>
            </article>
        @endforeach
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('proveedores.index') }}" class="supplier-filter-grid">
            <label class="form-field supplier-filter-grid__search">
                <span>Buscar</span>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span>
                    <input type="search" name="q" value="{{ request('q') }}"
                        placeholder="Razón social, RUC o contacto">
                </div>
            </label>

            <label class="form-field">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="1" @selected(request('estado') === '1')>Activo</option>
                    <option value="0" @selected(request('estado') === '0')>Inactivo</option>
                </select>
            </label>

            <div class="filter-actions">
                <button type="submit" class="button button--primary">
                    <x-ui.icon name="filter" :size="17" /> Filtrar
                </button>
                <a href="{{ route('proveedores.index') }}" class="button button--ghost">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="panel {{ $proveedores->isEmpty() ? 'panel--empty-list' : '' }}">
        @if ($proveedores->isNotEmpty())
            <div class="table-wrap table-wrap--responsive">
                <table class="data-table data-table--actions supplier-list-table">
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th>Contacto</th>
                            <th>Ubicación</th>
                            <th class="text-center">Cotizaciones</th>
                            <th class="text-center">Compras</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($proveedores as $proveedor)
                            <tr>
                                <td>
                                    <a href="{{ route('proveedores.show', $proveedor->id) }}"
                                        class="table-primary-link">
                                        {{ $proveedor->nombreVisible() }}
                                    </a>
                                    <span>
                                        RUC {{ $proveedor->ruc }}
                                        @if ($proveedor->nombre_comercial)
                                            · {{ $proveedor->razon_social }}
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ $proveedor->contacto ?: 'Sin contacto' }}</strong>
                                    <span>{{ $proveedor->telefono ?: ($proveedor->correo ?: 'Sin datos') }}</span>
                                </td>
                                <td>{{ $proveedor->ubicacionVisible() ?: 'No registrada' }}</td>
                                <td class="text-center">{{ $proveedor->cotizaciones_vigentes_count }}</td>
                                <td class="text-center">{{ $proveedor->ordenes_compra_count }}</td>
                                <td>
                                    <span class="badge badge--{{ $proveedor->estado ? 'success' : 'danger' }}">
                                        {{ $proveedor->estado ? 'ACTIVO' : 'INACTIVO' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="{{ route('proveedores.show', $proveedor->id) }}"
                                            class="icon-button" title="Ver proveedor">
                                            <x-ui.icon name="eye" :size="17" />
                                        </a>
                                        <a href="{{ route('proveedores.edit', $proveedor->id) }}"
                                            class="icon-button" title="Editar proveedor">
                                            <x-ui.icon name="edit" :size="17" />
                                        </a>
                                        <form method="POST"
                                            action="{{ route('proveedores.toggle', $proveedor->id) }}"
                                            data-confirm="¿Confirmas cambiar el estado de este proveedor?">
                                            @csrf
                                            @method('PATCH')
                                            <button class="icon-button icon-button--{{ $proveedor->estado ? 'danger' : 'success' }}"
                                                title="{{ $proveedor->estado ? 'Desactivar' : 'Activar' }}">
                                                <x-ui.icon name="power" :size="17" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$proveedores" />
        @else
            <div class="empty-table-state">
                <span class="empty-state__icon"><x-ui.icon name="suppliers" :size="30" /></span>
                <strong>No se encontraron proveedores</strong>
                <span>Ajusta los filtros o registra el primer proveedor.</span>
                <div class="empty-table-state__actions">
                    <a href="{{ route('proveedores.create') }}" class="button button--primary button--small">
                        <x-ui.icon name="plus" :size="16" /> Nuevo proveedor
                    </a>
                </div>
            </div>
        @endif
    </section>
@endsection
