document.addEventListener('DOMContentLoaded', () => {
    const documentRoot = document.querySelector('[data-commercial-document]');
    if (!documentRoot) return;

    const linesContainer = documentRoot.querySelector('[data-commercial-lines]');
    const addButton = documentRoot.querySelector('[data-add-commercial-line]');
    const currency = documentRoot.querySelector('[data-document-currency]');
    const exchangeField = documentRoot.querySelector('[data-exchange-field]');
    const exchangeInput = documentRoot.querySelector('[data-exchange-input]');
    const origin = documentRoot.querySelector('[data-proforma-origin]');
    const clientField = documentRoot.querySelector('[data-client-field]');
    const clientBox = clientField?.querySelector('[data-remote-combobox]')
        || documentRoot.querySelector('#cliente_id')?.closest('[data-remote-combobox]');
    const mode = documentRoot.dataset.documentMode;
    const productSearchUrl = documentRoot.dataset.productSearchUrl;
    const clientRelationsUrl = documentRoot.dataset.clientRelationsUrl;
    const clientValue = documentRoot.querySelector('[data-commercial-client]')
        || clientBox?.querySelector('[data-remote-combobox-value]');
    const orderType = documentRoot.querySelector('[data-commercial-order-type]');
    const address = documentRoot.querySelector('[data-commercial-address]');
    const vehicle = documentRoot.querySelector('[data-commercial-vehicle]');
    const vehicleField = documentRoot.querySelector('[data-commercial-vehicle-field]');
    const vehicleRequiredMark = documentRoot.querySelector('[data-vehicle-required-mark]');
    const vehicleHelp = documentRoot.querySelector('[data-vehicle-help]');
    const totals = {
        subtotal: documentRoot.querySelector('[data-document-subtotal]'),
        tax: documentRoot.querySelector('[data-document-tax]'),
        total: documentRoot.querySelector('[data-document-total]'),
    };

    const number = (value) => {
        const parsed = Number.parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const money = (value) => new Intl.NumberFormat('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);

    const symbol = () => currency?.value === 'USD' ? 'US$' : 'S/';

    const costInCurrency = (line) => {
        const costPen = number(line.dataset.costPen);
        if (currency?.value !== 'USD') return costPen;

        const exchange = number(exchangeInput?.value);
        return exchange > 0 ? costPen / exchange : 0;
    };

    const suggestedPrice = (line) => {
        const margin = number(documentRoot.dataset.margin);
        return costInCurrency(line) * (1 + margin / 100);
    };

    const linePrice = (line) => {
        if (mode === 'quote') {
            return number(line.querySelector('[data-line-price]')?.value);
        }

        return suggestedPrice(line);
    };

    const calculate = () => {
        let subtotal = 0;
        let tax = 0;
        let total = 0;

        linesContainer.querySelectorAll('[data-commercial-line]').forEach((line) => {
            const cost = costInCurrency(line);
            const suggested = suggestedPrice(line);
            const quantity = number(line.querySelector('[data-line-quantity]')?.value);
            const price = linePrice(line);
            const taxMode = line.querySelector('[data-line-tax]')?.value || 'AGREGAR';
            const costOutput = line.querySelector('[data-line-cost]');
            const suggestedOutput = line.querySelector('[data-line-suggested]');

            if (costOutput) costOutput.textContent = `${symbol()} ${money(cost)}`;
            if (suggestedOutput) suggestedOutput.textContent = `${symbol()} ${money(suggested)}`;

            let baseUnit = price;
            let taxUnit = 0;
            let totalUnit = price;

            if (taxMode === 'INCLUIDO') {
                baseUnit = price / 1.18;
                taxUnit = price - baseUnit;
            } else if (taxMode === 'AGREGAR') {
                taxUnit = price * 0.18;
                totalUnit = price + taxUnit;
            }

            subtotal += quantity * baseUnit;
            tax += quantity * taxUnit;
            total += quantity * totalUnit;
        });

        if (totals.subtotal) totals.subtotal.textContent = `${symbol()} ${money(subtotal)}`;
        if (totals.tax) totals.tax.textContent = `${symbol()} ${money(tax)}`;
        if (totals.total) totals.total.textContent = `${symbol()} ${money(total)}`;
    };

    const updateIndexes = () => {
        linesContainer.querySelectorAll('[data-commercial-line]').forEach((line, index) => {
            const numberOutput = line.querySelector('[data-line-number]');
            if (numberOutput) numberOutput.textContent = String(index + 1);

            line.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/detalles\[\d+\]/, `detalles[${index}]`);
            });
        });
    };

    const selectedProductIds = (exceptLine = null) => Array.from(
        linesContainer.querySelectorAll('[data-commercial-line]')
    )
        .filter((line) => line !== exceptLine)
        .map((line) => line.querySelector('[data-line-product-id]')?.value)
        .filter(Boolean);

    const bindLine = (line) => {
        if (line.dataset.bound === 'true') return;
        line.dataset.bound = 'true';

        const box = line.querySelector('[data-remote-combobox]');
        const search = box?.querySelector('[data-remote-combobox-search]');
        const priceInput = line.querySelector('[data-line-price]');

        window.HidroilRemoteCombobox?.initialize(box);

        box?.addEventListener('remote-combobox:selected', (event) => {
            const item = event.detail || {};

            if (selectedProductIds(line).includes(String(item.id))) {
                window.HidroilRemoteCombobox?.clear(box, true);
                search?.setCustomValidity('Este producto ya está agregado al documento.');
                search?.reportValidity();
                return;
            }

            search?.setCustomValidity('');
            line.dataset.costPen = String(item.costo_referencia || 0);
            line.dataset.stock = String(item.stock || 0);
            line.dataset.unit = item.unidad || '';

            const meta = line.querySelector('[data-product-meta]');
            if (meta) {
                meta.textContent = `${item.unidad || 'Sin unidad'} · Stock ${number(item.stock).toFixed(3)}`;
            }

            if (mode === 'quote' && priceInput && number(priceInput.value) <= 0) {
                priceInput.value = suggestedPrice(line).toFixed(4);
            }

            calculate();
        });

        line.querySelector('[data-remove-commercial-line]')?.addEventListener('click', () => {
            const lines = linesContainer.querySelectorAll('[data-commercial-line]');
            if (lines.length === 1) {
                window.HidroilRemoteCombobox?.clear(box, true);
                line.dataset.costPen = '0';
                const quantity = line.querySelector('[data-line-quantity]');
                if (quantity) quantity.value = '1';
                if (priceInput) priceInput.value = '';
            } else {
                line.remove();
                updateIndexes();
            }
            calculate();
        });

        line.querySelectorAll('input, select').forEach((field) => {
            field.addEventListener('input', calculate);
            field.addEventListener('change', calculate);
        });
    };

    const newLine = () => {
        const line = document.createElement('article');
        line.className = `commercial-line${mode === 'quote' ? ' commercial-line--quote' : ''}`;
        line.dataset.commercialLine = '';
        line.dataset.costPen = '0';
        line.dataset.stock = '0';
        line.dataset.unit = '';
        const commercialFields = mode === 'quote'
            ? `
                <div class="commercial-line__suggestion"><span>Precio sugerido</span><strong data-line-suggested>${symbol()} 0.00</strong><small>Referencia automática</small></div>
                <label class="form-field commercial-line__price"><span>Precio cotizado <span class="required-mark">*</span></span><input type="number" name="detalles[0][precio_unitario]" min="0.0001" step="0.0001" data-line-price required></label>
            `
            : `
                <div class="commercial-line__suggestion"><span>Costo referencial</span><strong data-line-cost>S/ 0.00</strong><small>Según inventario</small></div>
                <div class="commercial-line__suggestion"><span>Precio sugerido</span><strong data-line-suggested>S/ 0.00</strong><small>Referencia automática</small></div>
            `;

        line.innerHTML = `
            <div class="commercial-line__number" data-line-number>1</div>
            <div class="form-field commercial-line__product">
                <label>Producto <span class="required-mark">*</span></label>
                <div class="remote-combobox" data-remote-combobox data-search-url="${productSearchUrl}" data-empty-text="Producto no encontrado. Regístralo primero." data-required="true">
                    <div class="remote-combobox__control">
                        <input type="search" placeholder="Código o descripción" autocomplete="off" aria-autocomplete="list" aria-expanded="false" data-remote-combobox-search required>
                        <button type="button" class="remote-combobox__clear" aria-label="Limpiar selección" data-remote-combobox-clear hidden>&times;</button>
                    </div>
                    <input type="hidden" name="detalles[0][producto_id]" data-remote-combobox-value data-line-product-id>
                    <div class="remote-combobox__results" role="listbox" data-remote-combobox-results hidden></div>
                </div>
                <small data-product-meta>Selecciona un producto del catálogo.</small>
            </div>
            <label class="form-field commercial-line__quantity"><span>Cantidad <span class="required-mark">*</span></span><input type="number" name="detalles[0][cantidad]" min="0.001" step="0.001" value="1" data-line-quantity required></label>
            ${commercialFields}
            <label class="form-field commercial-line__tax"><span>IGV</span><select name="detalles[0][igv_modo]" data-line-tax><option value="AGREGAR">Agregar 18 %</option><option value="INCLUIDO">Incluido</option><option value="NO_APLICA">No aplica</option></select></label>
            <label class="form-field commercial-line__observation"><span>Observación</span><input type="text" name="detalles[0][observacion]" maxlength="300" placeholder="Opcional"></label>
            <button type="button" class="commercial-line__remove" data-remove-commercial-line aria-label="Quitar producto" title="Quitar producto">&times;</button>
        `;

        linesContainer.appendChild(line);
        updateIndexes();
        bindLine(line);
        line.querySelector('[data-remote-combobox-search]')?.focus();
        calculate();
    };

    const updateCurrency = () => {
        const usesUsd = currency?.value === 'USD';
        if (exchangeField) exchangeField.hidden = !usesUsd;
        if (exchangeInput) exchangeInput.required = usesUsd;
        calculate();
    };

    const updateOrigin = () => {
        if (!origin || !clientBox) return;
        const required = origin.value === 'VENTA_DIRECTA';
        const search = clientBox.querySelector('[data-remote-combobox-search]');
        const marker = clientField.querySelector('[data-client-required-mark]');
        clientBox.dataset.required = required ? 'true' : 'false';
        if (search) search.required = required;
        if (marker) marker.hidden = !required;
    };

    const orderCode = () => orderType?.tagName === 'SELECT'
        ? orderType.selectedOptions[0]?.dataset.orderCode || ''
        : orderType?.dataset.orderCode || '';

    const updateOrderContext = () => {
        if (!orderType || !vehicleField || !vehicle) return;

        const code = orderCode();
        const hidesVehicle = code === 'OP' || code === 'OV';
        const requiresVehicle = code === 'OM';

        vehicleField.hidden = hidesVehicle;
        vehicle.required = requiresVehicle;
        if (vehicleRequiredMark) vehicleRequiredMark.hidden = !requiresVehicle;

        if (hidesVehicle) vehicle.value = '';

        if (vehicleHelp) {
            vehicleHelp.textContent = requiresVehicle
                ? 'Obligatorio: identifica la unidad que recibirá el mantenimiento.'
                : 'Opcional para servicios que se ejecutan sobre una unidad existente.';
        }
    };

    const replaceRelationOptions = (select, placeholder, items) => {
        if (!select) return;

        select.replaceChildren();
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = placeholder;
        select.appendChild(empty);

        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.label;
            select.appendChild(option);
        });
    };

    const updateClientRelations = async (clientId) => {
        if (!clientRelationsUrl || (!address && !vehicle)) return;

        replaceRelationOptions(address, 'Cargando ubicaciones...', []);
        replaceRelationOptions(vehicle, 'Cargando vehículos...', []);

        if (!clientId) {
            replaceRelationOptions(address, 'Sin ubicación asociada', []);
            replaceRelationOptions(vehicle, 'Sin vehículo asociado', []);
            updateOrderContext();
            return;
        }

        try {
            const url = new URL(clientRelationsUrl, window.location.origin);
            url.searchParams.set('cliente_id', clientId);
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) throw new Error('No se pudieron cargar las relaciones.');

            const payload = await response.json();
            replaceRelationOptions(address, 'Sin ubicación asociada', payload.direcciones || []);
            replaceRelationOptions(vehicle, 'Sin vehículo asociado', payload.vehiculos || []);
        } catch (error) {
            replaceRelationOptions(address, 'No se pudieron cargar las ubicaciones', []);
            replaceRelationOptions(vehicle, 'No se pudieron cargar los vehículos', []);
        }

        updateOrderContext();
    };

    clientBox?.addEventListener('remote-combobox:selected', (event) => {
        documentRoot.dataset.margin = String(event.detail?.margen_porcentaje || 0);
        calculate();
        updateClientRelations(String(event.detail?.id || ''));
    });
    clientValue?.addEventListener('change', (event) => {
        if (!event.target.value) {
            documentRoot.dataset.margin = '0';
            calculate();
            updateClientRelations('');
        }
    });

    linesContainer.querySelectorAll('[data-commercial-line]').forEach(bindLine);
    addButton?.addEventListener('click', newLine);
    currency?.addEventListener('change', updateCurrency);
    exchangeInput?.addEventListener('input', calculate);
    origin?.addEventListener('change', updateOrigin);
    orderType?.addEventListener('change', updateOrderContext);

    updateIndexes();
    updateCurrency();
    updateOrigin();
    updateOrderContext();
    calculate();
});
