@php
    $editando = isset($requerimiento);
    $origen = old('origen', $origenInicial ?? 'REPOSICION');
    $prioridad = old('prioridad', $requerimiento->prioridad ?? 'NORMAL');
    $fecha = old('fecha_solicitud', isset($requerimiento) ? $requerimiento->fecha_solicitud?->format('Y-m-d') : now()->format('Y-m-d'));
@endphp

@if ($errors->any())
    <div class="notice notice--danger notice--block" role="alert">
        <x-ui.icon name="error" :size="18" />
        <div>
            <strong>Revisa el requerimiento.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    </div>
@endif

<section class="form-section purchase-requirement-form-section">
    <div class="form-section__heading">
        <span class="form-section__icon"><x-ui.icon name="requisitions" :size="20" /></span>
        <div>
            <p class="eyebrow">Necesidad de abastecimiento</p>
            <h2>Origen del requerimiento</h2>
            <p>Almacén formaliza qué debe conseguir Logística. Este documento no compra, no reserva y no mueve stock.</p>
        </div>
    </div>

    <div class="form-grid purchase-requirement-form-grid">
        <label class="form-field">
            <span>Fecha <span class="required-mark">*</span></span>
            <input type="date" name="fecha_solicitud" value="{{ $fecha }}" required>
            @error('fecha_solicitud')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="form-field">
            <span>Origen <span class="required-mark">*</span></span>
            <select name="origen" data-requirement-origin required>
                <option value="REPOSICION" @selected($origen === 'REPOSICION')>Reposición de stock</option>
                <option value="ORDEN_OPERACION" @selected($origen === 'ORDEN_OPERACION')>Faltante para OM / OS / OP</option>
            </select>
            <small>Solo Almacén emite el requerimiento.</small>
        </label>

        <label class="form-field">
            <span>Prioridad <span class="required-mark">*</span></span>
            <select name="prioridad" required>
                @foreach (['BAJA' => 'Baja', 'NORMAL' => 'Normal', 'ALTA' => 'Alta', 'URGENTE' => 'Urgente'] as $valor => $texto)
                    <option value="{{ $valor }}" @selected($prioridad === $valor)>{{ $texto }}</option>
                @endforeach
            </select>
            @error('prioridad')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <div class="form-field form-field--wide" data-requirement-order-field @if ($origen !== 'ORDEN_OPERACION') hidden @endif>
            <label for="orden_operacion_busqueda">Orden activa relacionada <span class="required-mark">*</span></label>
            <x-ui.remote-combobox
                name="orden_operacion_id"
                search-id="orden_operacion_busqueda"
                value-id="orden_operacion_id"
                :search-url="route('catalogos.ordenes-operacion.buscar')"
                :selected-id="$ordenSeleccionada?->id"
                :selected-label="$ordenSeleccionada
                    ? $ordenSeleccionada->codigo_orden.' — '.($ordenSeleccionada->cliente?->nombreVisible() ?? $ordenSeleccionada->descripcion)
                    : ''"
                placeholder="OM-0001, mantenimiento, OS, servicio, OP, producción o cliente"
                empty-text="No se encontró una orden activa. Solo se muestran órdenes EN_PROCESO."
            />
            <small>El requerimiento conservará la relación con la orden que originó el faltante.</small>
            @error('orden_operacion_id')<small class="field-error">{{ $message }}</small>@enderror
        </div>

        <label class="form-field form-field--wide">
            <span>Motivo / descripción</span>
            <textarea name="descripcion" rows="3" maxlength="500" placeholder="Ej. Reposición por stock mínimo o faltante necesario para completar la orden.">{{ old('descripcion', $requerimiento->descripcion ?? '') }}</textarea>
            @error('descripcion')<small class="field-error">{{ $message }}</small>@enderror
        </label>
    </div>
</section>

<section class="form-section purchase-requirement-products" data-requirement-products
    data-product-search-url="{{ route('catalogos.productos.buscar', ['contexto' => 'requerimiento_compra']) }}">
    <div class="form-section__heading form-section__heading--split">
        <span class="form-section__icon"><x-ui.icon name="products" :size="20" /></span>
        <div>
            <p class="eyebrow">Productos solicitados</p>
            <h2>¿Qué necesita abastecer Almacén?</h2>
            <p>Busca por código o descripción. El sistema muestra stock, reservas y una compra sugerida como referencia; Almacén decide la cantidad a solicitar.</p>
        </div>
    </div>

    <div class="purchase-requirement-product-search">
        <label class="form-field">
            <span>Buscar producto</span>
            <span class="input-with-icon">
                <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span>
                <input type="search" placeholder="Código o descripción" autocomplete="off" data-requirement-product-search>
            </span>
        </label>
        <div class="purchase-requirement-search-results" data-requirement-search-results hidden></div>
    </div>

    <x-ui.collapsible-notice title="Cómo se calcula la sugerencia" label="Ver cómo se obtiene la compra sugerida">
        <span>La referencia considera las reservas activas, el stock mínimo y el stock físico. No obliga a comprar esa cantidad y no genera ningún movimiento de inventario.</span>
    </x-ui.collapsible-notice>

    <div class="table-wrap table-wrap--wide purchase-requirement-table-wrap">
        <table class="data-table purchase-requirement-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Físico</th>
                    <th>Reservado</th>
                    <th>Disponible</th>
                    <th>Mínimo</th>
                    <th>Sugerido</th>
                    <th>Cantidad a solicitar</th>
                    <th>Observación</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody data-requirement-lines></tbody>
        </table>
    </div>

    <div class="purchase-requirement-empty" data-requirement-empty>
        <x-ui.icon name="products" :size="25" />
        <div>
            <strong>Aún no agregaste productos</strong>
            <span>Usa el buscador para añadir lo que Almacén necesita solicitar.</span>
        </div>
    </div>

    @error('detalles')<small class="field-error">{{ $message }}</small>@enderror
</section>

<div class="form-actions">
    <a href="{{ route('requerimientos-compra.index') }}" class="button button--ghost">Cancelar</a>
    <button type="submit" class="button button--primary">
        <x-ui.icon name="check" :size="17" />
        {{ $editando ? 'Guardar cambios' : 'Guardar borrador' }}
    </button>
</div>

@push('scripts')
<script>
(() => {
    const root = document.querySelector('[data-requirement-products]');
    if (!root) return;

    const origin = document.querySelector('[data-requirement-origin]');
    const orderField = document.querySelector('[data-requirement-order-field]');
    const orderInput = document.getElementById('orden_operacion_id');
    const search = root.querySelector('[data-requirement-product-search]');
    const results = root.querySelector('[data-requirement-search-results]');
    const body = root.querySelector('[data-requirement-lines]');
    const empty = root.querySelector('[data-requirement-empty]');
    const baseUrl = root.dataset.productSearchUrl;
    let timer = null;
    let lines = @json($detallesIniciales ?? []);

    const number = value => {
        const parsed = Number(value ?? 0);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const display = value => number(value).toLocaleString('es-PE', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const syncOrigin = () => {
        const usesOrder = origin?.value === 'ORDEN_OPERACION';
        if (orderField) orderField.hidden = !usesOrder;
        if (!usesOrder && orderInput) {
            orderInput.value = '';
            orderInput.dispatchEvent(new Event('change', { bubbles: true }));
            const orderSearch = document.getElementById('orden_operacion_busqueda');
            if (orderSearch) orderSearch.value = '';
        }
    };

    const render = () => {
        body.innerHTML = '';
        empty.hidden = lines.length > 0;

        lines.forEach((line, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <strong>${escapeHtml(line.codigo)}</strong>
                    <span>${escapeHtml(line.descripcion)}</span>
                    <small>${escapeHtml(line.unidad || 'Sin unidad')}</small>
                    <input type="hidden" name="detalles[${index}][producto_id]" value="${Number(line.producto_id)}">
                </td>
                <td><strong>${display(line.stock_fisico)}</strong></td>
                <td>${display(line.reservado)}</td>
                <td><span class="${number(line.disponible) < 0 ? 'text-danger' : ''}">${display(line.disponible)}</span></td>
                <td>${display(line.stock_minimo)}</td>
                <td>
                    <strong>${display(line.cantidad_sugerida)}</strong>
                    ${number(line.cantidad_sugerida) > 0 ? `<button type="button" class="text-link purchase-requirement-use-suggested" data-use-suggested="${index}">Usar sugerido</button>` : '<small>Sin faltante calculado</small>'}
                </td>
                <td>
                    <input class="purchase-requirement-quantity" type="number" min="0.001" step="0.001" name="detalles[${index}][cantidad_solicitada]" value="${escapeHtml(line.cantidad_solicitada ?? 1)}" required data-line-quantity="${index}">
                </td>
                <td>
                    <input type="text" maxlength="300" name="detalles[${index}][observacion]" value="${escapeHtml(line.observacion ?? '')}" placeholder="Opcional">
                </td>
                <td>
                    <button type="button" class="icon-button icon-button--danger" title="Quitar producto" aria-label="Quitar producto" data-remove-line="${index}">
                        <x-ui.icon name="close" :size="16" />
                    </button>
                </td>`;
            body.appendChild(row);
        });
    };

    const addLine = item => {
        if (lines.some(line => Number(line.producto_id) === Number(item.id))) {
            search.value = '';
            results.hidden = true;
            return;
        }

        lines.push({
            producto_id: Number(item.id),
            codigo: item.codigo,
            descripcion: item.descripcion,
            unidad: item.unidad,
            stock_fisico: number(item.stock_fisico),
            reservado: number(item.reservado),
            disponible: number(item.disponible),
            stock_minimo: number(item.stock_minimo),
            cantidad_sugerida: number(item.cantidad_sugerida),
            cantidad_solicitada: number(item.cantidad_sugerida) > 0 ? number(item.cantidad_sugerida) : 1,
            observacion: '',
        });
        search.value = '';
        results.hidden = true;
        render();
    };

    const renderResults = items => {
        results.innerHTML = '';
        if (!items.length) {
            results.innerHTML = '<div class="purchase-requirement-search-empty">No se encontraron productos activos.</div>';
            results.hidden = false;
            return;
        }

        items.forEach(item => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'purchase-requirement-search-result';
            button.innerHTML = `<strong>${escapeHtml(item.codigo)} — ${escapeHtml(item.descripcion)}</strong><span>${escapeHtml(item.description || '')}</span>`;
            button.addEventListener('click', () => addLine(item));
            results.appendChild(button);
        });
        results.hidden = false;
    };

    const runSearch = async () => {
        const q = search.value.trim();
        if (q.length < 1) {
            results.hidden = true;
            return;
        }

        const url = new URL(baseUrl, window.location.origin);
        url.searchParams.set('q', q);
        if (origin?.value === 'ORDEN_OPERACION' && orderInput?.value) {
            url.searchParams.set('orden_id', orderInput.value);
        }

        try {
            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('search');
            const payload = await response.json();
            renderResults(payload.items || []);
        } catch (_) {
            results.innerHTML = '<div class="purchase-requirement-search-empty">No se pudo consultar el catálogo. Intenta nuevamente.</div>';
            results.hidden = false;
        }
    };

    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(runSearch, 250);
    });

    body.addEventListener('click', event => {
        const remove = event.target.closest('[data-remove-line]');
        if (remove) {
            lines.splice(Number(remove.dataset.removeLine), 1);
            render();
            return;
        }

        const suggested = event.target.closest('[data-use-suggested]');
        if (suggested) {
            const index = Number(suggested.dataset.useSuggested);
            const input = body.querySelector(`[data-line-quantity="${index}"]`);
            if (input) input.value = lines[index].cantidad_sugerida;
        }
    });

    document.addEventListener('click', event => {
        if (!root.contains(event.target)) results.hidden = true;
    });

    origin?.addEventListener('change', syncOrigin);
    syncOrigin();
    render();
})();
</script>
@endpush
