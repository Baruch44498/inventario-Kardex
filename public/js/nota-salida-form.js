document.addEventListener('DOMContentLoaded', () => {
    const originForm = document.querySelector('[data-output-origin-form]');
    const originType = originForm?.querySelector('[data-output-origin-type]');

    originType?.addEventListener('change', () => originForm.submit());

    const formatQuantity = (value) => Number(value || 0).toLocaleString('es-PE', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });

    const initializeOutputRow = (row) => {
        if (!row || row.dataset.outputInitialized === '1') return;
        row.dataset.outputInitialized = '1';

        const quantity = row.querySelector('[data-output-quantity]');
        const treatment = row.querySelector('[data-output-treatment]');
        const toolWarning = row.querySelector('[data-last-tool-warning]');
        const planWarning = row.querySelector('[data-plan-warning]');
        const reservationWarning = row.querySelector('[data-reservation-warning]');
        const committedWarning = row.querySelector('[data-committed-stock-warning]');
        const fillPending = row.querySelector('[data-fill-pending]');

        const refresh = () => {
            if (!quantity || !treatment) return;

            const stockTotal = Number(row.dataset.stockTotal || quantity.dataset.outputTotalStock || 0);
            const reservedOrder = Number(row.dataset.reservaOrden || 0);
            const reservedGlobal = Number(row.dataset.reservadoGlobal || 0);
            const toolsInUse = Number(row.dataset.herramientasEnUso || 0);
            const outputMotive = row.dataset.outputMotivo || '';
            const plannedForOrder = row.dataset.planificadoOrden === '1';
            const pendingRequired = Number(row.dataset.pendienteOrden || 0);
            const value = Number(quantity.value || 0);
            const isTemporary = treatment.value === 'USO_TEMPORAL';
            const isConsumption = treatment.value === 'CONSUMO';

            if (toolWarning) {
                const lastUnits = isTemporary && value > 0 && value >= stockTotal;
                const warningText = toolWarning.dataset.warningText
                    || 'no quedarán unidades disponibles de esta herramienta en Almacén';
                toolWarning.hidden = !lastUnits;
                toolWarning.textContent = lastUnits
                    ? `⚠️ Si confirmas, ${warningText}.${toolsInUse > 0 ? ` Actualmente ${formatQuantity(toolsInUse)} ya están en uso.` : ''}`
                    : '';
            }

            let appliedReservation = 0;
            if (isConsumption && reservedOrder > 0) {
                appliedReservation = Math.min(value, reservedOrder);
            }
            const unreservedWithdrawal = Math.max(0, value - appliedReservation);
            const freeBefore = stockTotal - reservedGlobal;
            const committedAffected = Math.max(0, unreservedWithdrawal - Math.max(0, freeBefore));

            if (planWarning) {
                const appliesOrderPlan = outputMotive === 'ORDEN_OPERACION' && isConsumption && value > 0;
                const unplanned = appliesOrderPlan && !plannedForOrder;
                const alreadyAttended = appliesOrderPlan && plannedForOrder && pendingRequired <= 0.0001;
                const exceedsPending = appliesOrderPlan && plannedForOrder && pendingRequired > 0.0001 && value > pendingRequired;

                planWarning.hidden = !(unplanned || alreadyAttended || exceedsPending);
                planWarning.textContent = unplanned
                    ? '⚠️ Este consumo no figura en los materiales requeridos de la orden. Se permitirá y quedará como consumo real no planificado.'
                    : (alreadyAttended
                        ? '⚠️ El requerimiento planificado de este producto ya fue atendido. Esta cantidad quedará como consumo real adicional.'
                        : (exceedsPending
                            ? `⚠️ ${formatQuantity(value - pendingRequired)} exceden el pendiente planificado. El exceso quedará registrado como consumo real adicional.`
                            : ''));
            }

            if (reservationWarning) {
                const appliesOrderReservation = outputMotive === 'ORDEN_OPERACION' && isConsumption;
                const noReservation = appliesOrderReservation && value > 0 && reservedOrder <= 0;
                const exceedsReservation = appliesOrderReservation && value > reservedOrder && reservedOrder > 0;
                reservationWarning.hidden = !(noReservation || exceedsReservation);
                reservationWarning.textContent = noReservation
                    ? '⚠️ Este material no tiene reserva para esta orden. La salida se permitirá como consumo no planificado.'
                    : (exceedsReservation
                        ? `⚠️ ${formatQuantity(value - reservedOrder)} exceden la reserva pendiente de esta orden. El exceso se permitirá.`
                        : '');
            }

            if (committedWarning) {
                committedWarning.hidden = !(value > 0 && committedAffected > 0.0001);
                committedWarning.textContent = committedAffected > 0.0001
                    ? `⚠️ Esta salida usará ${formatQuantity(committedAffected)} de stock comprometido para otras órdenes. No se bloquea, pero aumenta el faltante de abastecimiento.`
                    : '';
            }
        };

        fillPending?.addEventListener('click', () => {
            if (!quantity) return;
            const pending = Number(row.dataset.pendienteOrden || 0);
            const stock = Number(quantity.dataset.outputStock || 0);
            quantity.value = String(Math.min(pending, stock));
            refresh();
        });

        quantity?.addEventListener('input', refresh);
        treatment?.addEventListener('change', refresh);
        refresh();
    };

    document.querySelectorAll('[data-output-row]').forEach(initializeOutputRow);

    const tableBody = document.querySelector('.output-lines-table tbody');
    const extraTemplate = document.querySelector('[data-output-extra-row-template]');
    const extraBox = document.querySelector('#producto_extra_salida_id')?.closest('[data-remote-combobox]');
    const submitButton = document.querySelector('[data-note-wizard-submit]');
    let nextDetailIndex = document.querySelectorAll('[data-output-row]').length;

    const detailField = (row, field, index, value = '') => {
        const input = row.querySelector(`[data-detail-field="${field}"]`);
        if (!input) return;
        input.name = `detalles[${index}][${field}]`;
        input.value = value ?? '';
    };

    const findRowByInventory = (inventoryId) => Array.from(document.querySelectorAll('[data-output-row]'))
        .find((row) => {
            const input = row.querySelector('input[name$="[inventario_id]"]');
            return input && String(input.value) === String(inventoryId);
        });

    const addExtraRow = (item) => {
        if (!extraTemplate || !tableBody || !item?.inventario_id) return;

        const existing = findRowByInventory(item.inventario_id);
        if (existing) {
            existing.scrollIntoView({ behavior: 'smooth', block: 'center' });
            existing.querySelector('[data-output-quantity]')?.focus();
            return;
        }

        const fragment = extraTemplate.content.cloneNode(true);
        const row = fragment.querySelector('[data-output-row]');
        if (!row) return;
        const index = nextDetailIndex++;

        detailField(row, 'inventario_id', index, item.inventario_id);
        detailField(row, 'producto_id', index, item.producto_id);
        detailField(row, 'repisa_id', index, item.repisa_id);
        detailField(row, 'tratamiento', index, 'CONSUMO');
        detailField(row, 'cantidad', index, '0');
        detailField(row, 'motivo_excedente', index, '');
        detailField(row, 'observacion', index, '');

        row.dataset.productoId = String(item.producto_id || '');
        row.dataset.reservaOrden = String(item.reserva_orden || 0);
        row.dataset.reservadoGlobal = String(item.reservado_global || 0);
        row.dataset.stockTotal = String(item.stock_total_producto || item.stock_actual || 0);
        row.dataset.herramientasEnUso = String(item.herramientas_en_uso || 0);

        row.querySelector('[data-extra-code]').textContent = item.codigo || 'Producto';
        row.querySelector('[data-extra-description]').textContent = item.descripcion || 'Sin descripción';
        row.querySelector('[data-extra-shelf]').textContent = item.repisa || '—';
        row.querySelector('[data-extra-stock]').textContent = formatQuantity(item.stock_actual);
        row.querySelector('[data-extra-unit]').textContent = item.unidad || '';
        row.querySelector('[data-extra-available]').textContent = formatQuantity(item.disponible_libre);

        const quantity = row.querySelector('[data-output-quantity]');
        if (quantity) {
            quantity.max = String(item.stock_actual || 0);
            quantity.step = item.permite_fraccionamiento ? '0.01' : '1';
            quantity.dataset.outputStock = String(item.stock_actual || 0);
            quantity.dataset.outputTotalStock = String(item.stock_total_producto || item.stock_actual || 0);
        }

        row.querySelector('[data-remove-extra-row]')?.addEventListener('click', () => row.remove());
        tableBody.appendChild(row);
        initializeOutputRow(row);
        if (submitButton) submitButton.disabled = false;
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        quantity?.focus();
    };

    extraBox?.addEventListener('remote-combobox:selected', (event) => {
        addExtraRow(event.detail || {});
        window.setTimeout(() => window.HidroilRemoteCombobox?.clear(extraBox, false), 0);
    });
});
