@extends('layouts.app')

@section('title', 'Nueva cotización de proveedor')
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Nueva cotización')

@section('content')
    @php
        $detalleIdsContexto = collect(request('detalle_ids', []))
            ->when(
                is_string(request('detalle_ids')),
                fn ($ids) => collect(explode(',', (string) request('detalle_ids')))
            )
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $contextoRegistro = array_filter([
            'requisicion_id' => $requisicionSeleccionada?->id,
            'proveedor_id' => $proveedorSeleccionado?->id,
            'detalle_ids' => $detalleIdsContexto->all(),
        ], fn ($value) => $value !== null && $value !== []);
        $modoImportar = ! $importacionAsistida && request('modo') === 'importar';
        $volverUrl = $requisicionSeleccionada
            ? route('requerimientos-compra.show', $requisicionSeleccionada)
            : route('cotizaciones-proveedor.index');
    @endphp

    <a href="{{ $volverUrl }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        {{ $requisicionSeleccionada ? 'Volver al requerimiento' : 'Volver a cotizaciones' }}
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Oferta recibida</p>
            <h1>Registrar cotización de proveedor</h1>
            <p>Un solo registro, ya sea digitado manualmente o precargado desde PDF/Excel. No genera inventario ni compromete una compra.</p>
        </div>
    </section>

    @if (! $importacionAsistida)
        <section class="supplier-quote-entry-methods" aria-labelledby="metodo-registro-title">
            <div class="supplier-quote-entry-methods__heading">
                <p class="eyebrow">Método de ingreso</p>
                <h2 id="metodo-registro-title">¿Cómo registrarás la cotización?</h2>
                <p>Ambas opciones terminan en el mismo formulario y requieren revisión antes de guardar.</p>
            </div>
            <div class="supplier-quote-entry-methods__options">
                <a href="{{ route('cotizaciones-proveedor.create', [...$contextoRegistro, 'modo' => 'manual']) }}"
                    class="supplier-quote-entry-option {{ ! $modoImportar ? 'is-active' : '' }}"
                    @if (! $modoImportar) aria-current="true" @endif>
                    <span class="supplier-quote-entry-option__icon"><x-ui.icon name="edit" :size="20" /></span>
                    <span><strong>Ingreso manual</strong><small>Completar los datos directamente.</small></span>
                </a>
                <a href="{{ route('cotizaciones-proveedor.create', [...$contextoRegistro, 'modo' => 'importar']) }}"
                    class="supplier-quote-entry-option {{ $modoImportar ? 'is-active' : '' }}"
                    @if ($modoImportar) aria-current="true" @endif>
                    <span class="supplier-quote-entry-option__icon"><x-ui.icon name="upload" :size="20" /></span>
                    <span><strong>Importar PDF / Excel</strong><small>Extraer datos y precargar este formulario.</small></span>
                </a>
            </div>
        </section>
    @endif

    @if (! $importacionAsistida && $borradoresImportacion->isNotEmpty())
        <details class="supplier-quote-drafts">
            <summary>
                <span><strong>Borradores de importación pendientes</strong><small>{{ $borradoresImportacion->count() }} disponible(s) para continuar o descartar</small></span>
                <x-ui.icon name="chevron-down" :size="18" />
            </summary>
            <div class="supplier-quote-drafts__list">
                @foreach ($borradoresImportacion as $borrador)
                    <article>
                        <div>
                            <strong>{{ $borrador->nombre_original }}</strong>
                            <span>{{ $borrador->requisicion?->codigo ?? 'Sin requerimiento' }} · {{ $borrador->proveedor?->nombreVisible() ?? 'Proveedor por revisar' }}</span>
                            <small>Procesado {{ $borrador->updated_at?->diffForHumans() }}</small>
                        </div>
                        <div class="supplier-quote-drafts__actions">
                            <a href="{{ route('cotizaciones-proveedor.importacion.reanudar', $borrador) }}" class="button button--ghost button--small">Continuar</a>
                            <form method="POST" action="{{ route('cotizaciones-proveedor.importacion.destroy', $borrador) }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="volver_registro" value="1">
                                <button type="submit" class="button button--ghost button--small">Descartar</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        </details>
    @endif

    @if ($modoImportar)
        @include('cotizaciones_proveedor._importacion_form')
    @else
        @if ($importacionAsistida)
            <div class="supplier-quote-import-review">
                <div class="supplier-quote-import-review__icon"><x-ui.icon name="file" :size="22" /></div>
                <div class="supplier-quote-import-review__copy">
                    <p class="eyebrow">Documento precargado · revisión obligatoria</p>
                    <strong>{{ $importacionAsistida->nombre_original }}</strong>
                    <span>Origen: {{ $importacionAsistida->tipo_archivo }}. Los campos no detectados permanecen pendientes; revisa proveedor, productos, cantidades, precios, descuentos e IGV.</span>
                    @if (collect($importacionAsistida->advertencias)->isNotEmpty())
                        <details open>
                            <summary>{{ collect($importacionAsistida->advertencias)->count() }} advertencia{{ collect($importacionAsistida->advertencias)->count() === 1 ? '' : 's' }} de extracción</summary>
                            <ul>
                                @foreach (collect($importacionAsistida->advertencias) as $advertencia)
                                    <li>{{ $advertencia }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
                <div class="supplier-quote-import-review__actions">
                    <form method="POST" action="{{ route('cotizaciones-proveedor.importacion.continuar-manual', $importacionAsistida) }}">
                        @csrf
                        <button type="submit" class="button button--ghost button--small">Continuar manualmente</button>
                    </form>
                    <form method="POST" action="{{ route('cotizaciones-proveedor.importacion.destroy', $importacionAsistida) }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="volver_importar" value="1">
                        <button type="submit" class="button button--ghost button--small">Reemplazar documento</button>
                    </form>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('cotizaciones-proveedor.store') }}" data-dirty-form>
            @csrf
            @include('cotizaciones_proveedor._form')
        </form>
    @endif
@endsection
