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
    const fiscalWarning = documentRoot.querySelector('[data-fiscal-warning]');
    const commercialDetail = {
        eyebrow: documentRoot.querySelector('[data-commercial-detail-eyebrow]'),
        title: documentRoot.querySelector('[data-commercial-detail-title]'),
        description: documentRoot.querySelector('[data-commercial-detail-description]'),
        addLabel: documentRoot.querySelector('[data-commercial-add-line-label]'),
        noteTitle: documentRoot.querySelector('[data-commercial-detail-note-title]'),
        noteText: documentRoot.querySelector('[data-commercial-detail-note-text]'),
    };
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
            const marginOutput = line.querySelector('[data-line-margin]');

            if (costOutput) costOutput.textContent = `${symbol()} ${money(cost)}`;
            if (suggestedOutput) suggestedOutput.textContent = `${symbol()} ${money(suggested)}`;
            if (marginOutput) {
                marginOutput.textContent = money(number(documentRoot.dataset.margin));
            }

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

            search?.setCustomValidity('');
            if (mode === 'quote') {
                line.dataset.costPen = String(item.costo_referencia || 0);
            }
            line.dataset.stock = String(item.stock || 0);
            line.dataset.unit = item.unidad || '';

            const meta = line.querySelector('[data-product-meta]');
            if (meta) {
                meta.textContent = `${item.unidad || 'Sin unidad'} · Stock ${number(item.stock).toFixed(2)}`;
            }

            if (mode === 'quote' && priceInput && number(priceInput.value) <= 0) {
                priceInput.value = suggestedPrice(line).toFixed(2);
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
        line.className = `commercial-line${mode === 'quote'
            ? ' commercial-line--quote'
            : ' commercial-line--request'}`;
        line.dataset.commercialLine = '';
        if (mode === 'quote') line.dataset.costPen = '0';
        line.dataset.stock = '0';
        line.dataset.unit = '';
        const commercialFields = mode === 'quote'
            ? `
                <label class="form-field commercial-line__price">
                    <span>Precio cotizado <span class="required-mark">*</span></span>
                    <input type="number" name="detalles[0][precio_unitario]" min="0.0001" step="0.0001" data-line-price required>
                    <small class="commercial-price-help">Sugerido: <strong data-line-suggested>${symbol()} 0.00</strong> · Margen: <span data-line-margin>${money(number(documentRoot.dataset.margin))}</span> %</small>
                </label>
            `
            : '';
        const taxField = mode === 'quote'
            ? '<label class="form-field commercial-line__tax"><span>IGV</span><select name="detalles[0][igv_modo]" data-line-tax><option value="AGREGAR">Agregar 18 %</option><option value="INCLUIDO">Incluido</option><option value="NO_APLICA">No aplica</option></select></label>'
            : '';
        const requestTreatment = mode === 'quote'
            ? ''
            : '<label class="form-field commercial-line__treatment"><span>Tratamiento <span class="required-mark">*</span></span><select name="detalles[0][tratamiento]" required><option value="VENTA">Venta</option><option value="PRESTAMO">Préstamo / reposición</option></select><small>El préstamo no se incluye en el monto a cobrar.</small></label>';
        const requestReference = mode === 'quote'
            ? ''
            : '<label class="form-field commercial-line__observation"><span>Referencia para Logística</span><input type="text" name="detalles[0][observacion]" maxlength="300" placeholder="Marca, presentación u otra indicación (opcional)"></label>';

        line.innerHTML = `
            <div class="commercial-line__number" data-line-number>1</div>
            <div class="form-field commercial-line__product">
                <label>Producto <span class="required-mark">*</span></label>
                <div class="remote-combobox" data-remote-combobox data-search-url="${productSearchUrl}" data-empty-text="Producto no encontrado o sin stock físico disponible en Almacén." data-required="true">
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
            ${taxField}
            ${requestTreatment}
            ${requestReference}
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
        if (exchangeInput) {
            exchangeInput.required = usesUsd;
            if (!usesUsd) exchangeInput.value = '';
        }
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

    const updateCommercialDetailContext = (code) => {
        if (!commercialDetail.title) return;

        if (code === 'OP') {
            if (commercialDetail.eyebrow) commercialDetail.eyebrow.textContent = 'Uso interno · No se muestra al cliente';
            commercialDetail.title.textContent = 'Composición interna y valorización';
            if (commercialDetail.description) {
                commercialDetail.description.textContent = 'Registra los materiales previstos para fabricar la OP. El cliente verá la capacidad o descripción del trabajo y el importe final, no esta composición.';
            }
            if (commercialDetail.addLabel) commercialDetail.addLabel.textContent = 'Agregar componente';
            if (commercialDetail.noteTitle) commercialDetail.noteTitle.textContent = 'Composición reservada para HIDROIL';
            if (commercialDetail.noteText) {
                commercialDetail.noteText.textContent = 'Al aprobar la cotización, estos productos pasarán como materiales previstos de la OP. Los costos de referencia, sugeridos y márgenes permanecen internos.';
            }
            return;
        }

        if (code === 'OM' || code === 'OS') {
            if (commercialDetail.eyebrow) commercialDetail.eyebrow.textContent = 'Detalle comercial';
            commercialDetail.title.textContent = 'Materiales y repuestos cotizados';
            if (commercialDetail.description) {
                commercialDetail.description.textContent = 'Registra los materiales o repuestos que forman parte del mantenimiento o servicio. Estos sí aparecerán en el detalle comercial del cliente.';
            }
            if (commercialDetail.addLabel) commercialDetail.addLabel.textContent = 'Agregar material / repuesto';
            if (commercialDetail.noteTitle) commercialDetail.noteTitle.textContent = 'Detalle visible para el cliente';
            if (commercialDetail.noteText) {
                commercialDetail.noteText.textContent = 'Cantidad, precio cotizado e IGV de estos materiales o repuestos formarán parte de la cotización del cliente. Los costos de referencia, sugeridos y márgenes siguen siendo internos.';
            }
            return;
        }

        if (commercialDetail.eyebrow) commercialDetail.eyebrow.textContent = 'Detalle de materiales';
        commercialDetail.title.textContent = 'Productos de la cotización';
        if (commercialDetail.description) commercialDetail.description.textContent = 'Selecciona OM, OS u OP para definir cómo se mostrará este detalle al cliente.';
        if (commercialDetail.addLabel) commercialDetail.addLabel.textContent = 'Agregar material / repuesto';
        if (commercialDetail.noteTitle) commercialDetail.noteTitle.textContent = 'Define primero el tipo de trabajo';
        if (commercialDetail.noteText) {
            commercialDetail.noteText.textContent = 'En Producción la composición será interna; en Mantenimiento y Servicio los materiales o repuestos sí formarán parte del detalle comercial.';
        }
    };

    const updateOrderContext = () => {
        if (!orderType) return;

        const code = orderCode();
        updateCommercialDetailContext(code);

        if (!vehicleField || !vehicle) return;

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

    const updateFiscalWarning = (requiresFiscal, hasFiscal) => {
        if (!fiscalWarning) return;
        fiscalWarning.hidden = !(requiresFiscal && !hasFiscal);
    };

    const updateClientRelations = async (clientId) => {
        if (!clientRelationsUrl || (!address && !vehicle)) return;

        replaceRelationOptions(address, 'Cargando ubicaciones...', []);
        replaceRelationOptions(vehicle, 'Cargando vehículos...', []);

        if (!clientId) {
            replaceRelationOptions(address, 'Sin ubicación asociada', []);
            replaceRelationOptions(vehicle, 'Sin vehículo asociado', []);
            updateFiscalWarning(false, false);
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
            updateFiscalWarning(
                Boolean(payload.requiere_direccion_fiscal),
                Boolean(payload.tiene_direccion_fiscal)
            );
        } catch (error) {
            replaceRelationOptions(address, 'No se pudieron cargar las ubicaciones', []);
            replaceRelationOptions(vehicle, 'No se pudieron cargar los vehículos', []);
            updateFiscalWarning(false, false);
        }

        updateOrderContext();
    };

    clientBox?.addEventListener('remote-combobox:selected', (event) => {
        if (mode === 'quote') {
            documentRoot.dataset.margin = String(event.detail?.margen_porcentaje || 0);
            calculate();
        }
        updateClientRelations(String(event.detail?.id || ''));
    });
    clientValue?.addEventListener('change', (event) => {
        if (!event.target.value) {
            if (mode === 'quote') {
                documentRoot.dataset.margin = '0';
                calculate();
            }
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
