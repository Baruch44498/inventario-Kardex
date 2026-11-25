const { expect, test: base } = require('@playwright/test');
const escenario = require('./.runtime/escenario.json');
const { pantallas, sesiones } = require('./.runtime/barrido.json');
const test = base.extend({ credenciales: [escenario, { option: true }] });

async function comprobarAncho(page, estado, evidencia) {
    const dimensiones = await page.evaluate(() => ({
        ventana: document.documentElement.clientWidth,
        documento: document.documentElement.scrollWidth,
    }));
    expect.soft(dimensiones.ventana, `${estado}: ancho móvil activo`).toBe(page.viewportSize().width);
    const limite = dimensiones.ventana + 1;

    if (dimensiones.documento > limite) {
        await evidencia.attach(estado, {
            body: await page.screenshot({ fullPage: true }),
            contentType: 'image/png',
        });
    }

    expect.soft(dimensiones.documento, `${estado}: ancho de página en un viewport de ${dimensiones.ventana}px`)
        .toBeLessThanOrEqual(limite);
}

test.beforeEach(async ({ page, credenciales }) => {
    await page.goto('/login');
    await page.locator('#email').fill(credenciales.email);
    await page.locator('#password').fill(credenciales.clave);
    await page.locator('[data-login-submit]').tap();
    await expect(page).not.toHaveURL(/\/login(?:[?#]|$)/);
});

async function comprobarListado(page, ruta, tabla, evidencia) {
    const fila = tabla.locator('tbody > tr:not(.table-details-row)').first();
    await expect(fila).toBeVisible();
    await expect(fila).toHaveCSS('display', 'grid');
    const desborde = await tabla.evaluate(elemento => elemento.parentElement.scrollWidth - elemento.parentElement.clientWidth);
    expect(desborde, 'El listado cabe dentro de su panel').toBeLessThanOrEqual(1);
    await comprobarAncho(page, ruta, evidencia);
}

async function comprobarPestanas(pestanas) {
    await expect(pestanas).toHaveCSS('display', 'grid');
    const desborde = await pestanas.evaluate(elemento => elemento.scrollWidth - elemento.clientWidth);
    expect(desborde, 'Las pestañas caben sin desplazamiento horizontal').toBeLessThanOrEqual(1);
}

test('Productos mantiene separado el filtro de marca y permite abrir los detalles', async ({ page }, evidencia) => {
    await page.goto(pantallas['productos.index'].ruta);
    const tabla = page.locator('.data-table--cards');
    await comprobarListado(page, 'Productos', tabla, evidencia);

    const marca = await page.getByLabel('Marca', { exact: true }).boundingBox();
    const filtrar = page.getByRole('button', { name: 'Filtrar' });
    const boton = await filtrar.boundingBox();
    expect(boton.y).toBeGreaterThanOrEqual(marca.y + marca.height);
    await page.getByLabel('Buscar', { exact: true }).fill(escenario.producto);
    await filtrar.tap();
    await expect(page).toHaveURL(new RegExp('q=' + escenario.producto));

    const desplegar = tabla.getByRole('button', { name: 'Ver más datos del producto ' + escenario.producto });
    const detalle = tabla.locator('.table-details-row');
    await desplegar.tap();
    await expect(desplegar).toHaveAttribute('aria-expanded', 'true');
    await expect(detalle.getByText('Marca', { exact: true })).toBeVisible();
    await comprobarListado(page, 'Productos con detalles', tabla, evidencia);
    await desplegar.tap();
    await expect(detalle).toBeHidden();
});

test.describe('Listados con acceso de Administración', () => {
    test.use({ credenciales: sesiones.ADMINISTRADOR });

    test('Cada tarjeta muestra sus datos y permite alcanzar las acciones', async ({ page }, evidencia) => {
        const listados = [
            'clientes', 'proveedores', 'repisas', 'empleados', 'usuarios', 'proformas',
            'cotizaciones-cliente', 'notas-ingreso', 'ordenes-operacion', 'solicitudes-compra',
        ];

        for (const listado of listados) {
            await test.step(listado, async () => {
                await page.goto(pantallas[listado + '.index'].ruta);
                const tabla = page.locator('.data-table--cards');
                await comprobarListado(page, listado, tabla, evidencia);
                const acciones = tabla.locator('tbody tr:first-child > td:last-child');
                const accion = acciones.locator('a, button').first();
                await accion.click({ trial: true });
            });
        }
    });

    test('Plantillas y gasto real muestran sus tablas y permiten desplegar detalles', async ({ page }, evidencia) => {
        await page.goto(pantallas['plantillas-costeo.index'].ruta);
        await comprobarListado(page, 'Plantillas activas', page.locator('.data-table--cards').last(), evidencia);
        await page.goto(pantallas['ordenes-operacion.gasto-real'].ruta);
        for (const detalle of await page.locator('main details:has(.data-table--cards)').all()) {
            if (await detalle.getAttribute('open') === null) {
                await detalle.locator('summary').tap();
            }
            await comprobarListado(page, 'Gasto real', detalle.locator('.data-table--cards'), evidencia);
        }
    });
});

test('Registro de Almacén permite buscar, seleccionar y abrir las acciones del producto', async ({ page }, evidencia) => {
    await page.goto(escenario.rutas.registro);
    const buscador = page.getByRole('combobox', { name: 'Buscar producto o repisa' });
    await expect(buscador).toBeVisible();
    await comprobarAncho(page, 'Registro de Almacén, búsqueda inicial', evidencia);
    await buscador.fill(escenario.producto);
    await expect(page.getByRole('option', { name: new RegExp(escenario.producto) })).toBeVisible();
    await comprobarAncho(page, 'Registro de Almacén, sugerencias visibles', evidencia);
    await page.getByRole('button', { name: 'Buscar', exact: true }).tap();
    await page.getByRole('link', { name: new RegExp(`^Seleccionar ${escenario.producto}`) }).tap();
    await expect(page.locator('#registro-producto-titulo')).toBeVisible();
    await comprobarAncho(page, 'Registro de Almacén, producto seleccionado', evidencia);
    await page.locator('[data-product-detail-tab="precios"]').tap();
    await expect(page.locator('#registro-precios')).toBeVisible();
    await page.getByRole('button', { name: 'Guardar referencia' }).click({ trial: true });
    await comprobarAncho(page, 'Registro de Almacén, precios de referencia', evidencia);
});

test('Inventario periódico permite buscar una repisa y comprobar la selección', async ({ page }, evidencia) => {
    await page.goto(escenario.rutas.inventario);
    const buscador = page.getByRole('searchbox', { name: 'Código de repisa' });
    await expect(buscador).toBeVisible();
    await comprobarAncho(page, 'Inventario periódico, apertura', evidencia);
    await buscador.fill(escenario.repisa);
    await page.getByRole('checkbox', { name: new RegExp(escenario.repisa) }).check();
    await page.locator('[data-shelf-summary] summary').tap();
    await expect(page.getByRole('button', { name: `Quitar repisa ${escenario.repisa}` })).toBeVisible();
    await page.locator('[data-open-shelf-count]').click({ trial: true });
    await comprobarAncho(page, 'Inventario periódico, repisa seleccionada', evidencia);
});

test('Orden de compra mantiene accesibles sus pestañas y la acción de recepción', async ({ page }, evidencia) => {
    await page.goto(escenario.rutas.orden);
    await expect(page.getByRole('heading', { name: escenario.orden })).toBeVisible();
    await comprobarPestanas(page.locator('.purchase-order-tabs'));
    await comprobarAncho(page, 'Orden de compra, apertura', evidencia);
    await page.getByRole('tab', { name: 'Resumen', exact: true }).tap();
    await expect(page.locator('#purchase-order-panel-resumen')).toBeVisible();
    await comprobarAncho(page, 'Orden de compra, resumen', evidencia);
    const productos = page.getByRole('tab', { name: /Productos y entregas/ });
    await productos.tap();
    await expect(productos).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('#purchase-order-panel-productos')).toBeVisible();
    const tabla = page.getByRole('region', { name: 'Productos y avance de recepción' }).getByRole('table');
    await comprobarListado(page, 'Orden de compra, productos y entregas', tabla, evidencia);
    await page.getByRole('tab', { name: 'Historial' }).tap();
    await expect(page.locator('#purchase-order-panel-historial')).toBeVisible();
    await page.getByRole('link', { name: 'Registrar recepción' }).click({ trial: true });
    await comprobarAncho(page, 'Orden de compra, historial', evidencia);
});

async function abrirPestana(page, selector) {
    const pestana = page.locator(selector);
    await pestana.tap();
    await expect(pestana).toHaveAttribute('aria-selected', 'true');
    await comprobarPestanas(pestana.locator('..'));
    const panel = page.locator('#' + await pestana.getAttribute('aria-controls'));
    await expect(panel).toBeVisible();
    return panel;
}

test('Almacén puede editar el requerimiento y alternar las pestañas del producto', async ({ page }, evidencia) => {
    await page.goto(pantallas['requerimientos-compra.edit'].ruta);
    const tabla = page.locator('.purchase-requirement-table');
    await comprobarListado(page, 'Requerimiento editable', tabla, evidencia);
    const cantidad = tabla.getByRole('spinbutton', { name: 'Cantidad a solicitar de ' + escenario.producto });
    await cantidad.fill('3');
    await expect(cantidad).toHaveValue('3');
    await tabla.getByRole('button', { name: 'Quitar producto' }).tap();
    await expect(cantidad).toHaveCount(0);
    await page.locator('[data-requirement-product-search]').fill(escenario.producto);
    await page.locator('[data-requirement-search-results]').getByRole('button', { name: new RegExp(escenario.producto) }).tap();
    await expect(cantidad).toBeVisible();
    await comprobarListado(page, 'Requerimiento con producto agregado', tabla, evidencia);

    await page.goto(pantallas['productos.show'].ruta);
    await comprobarPestanas(page.locator('.product-detail-tabs'));
    for (const pestana of await page.locator('.product-detail-tabs [role="tab"]').all()) {
        await pestana.tap();
        await expect(pestana).toHaveAttribute('aria-selected', 'true');
        await comprobarAncho(page, 'Detalle del producto', evidencia);
    }
});

test('La nota de salida conserva los productos elegidos al buscar y cambiar de página', async ({ page }, evidencia) => {
    await page.goto(pantallas['notas-salida.entrega-personal'].ruta);
    await page.locator('#recibido_por_empleado_id').selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Continuar a productos' }).tap();

    const search = page.getByRole('searchbox', { name: 'Buscar producto o repisa' });
    const rows = page.locator('.output-lines-table tbody tr:visible');
    const confirm = page.getByRole('button', { name: 'Revisar confirmación' });
    await expect(rows).toHaveCount(10);
    await expect(rows.first()).toHaveCSS('display', 'grid');
    await confirm.tap();
    await expect(page.getByText('Ingresa una cantidad mayor que cero en al menos un producto.')).toBeVisible();
    await expect(search).toBeFocused();

    const first = page.getByRole('spinbutton', { name: `Cantidad de ${escenario.producto} en ${escenario.repisa}`, exact: true });
    await first.fill('2');
    await page.getByRole('button', { name: 'Siguiente', exact: true }).tap();
    await expect(rows).toHaveCount(3);
    await expect(first).toBeHidden();

    await search.fill('valvula qa015 UI-SALIDA-11');
    await expect(rows).toHaveCount(1);
    const second = rows.getByRole('spinbutton');
    await second.fill('3');
    await rows.getByRole('combobox', { name: 'Tratamiento' }).selectOption('USO_TEMPORAL');
    await rows.locator('summary').tap();
    await rows.getByRole('textbox', { name: 'Observación' }).fill('Entregar al operador');

    await search.fill('sin coincidencias');
    await expect(page.getByText('No se encontraron productos ni repisas con esa búsqueda.')).toBeVisible();
    await page.getByRole('button', { name: 'Solo seleccionados (2)' }).tap();
    await expect(rows).toHaveCount(2);
    await expect(first).toHaveValue('2');
    await expect(second.last()).toHaveValue('3');
    await comprobarAncho(page, 'Nota de salida, productos seleccionados', evidencia);

    const datos = await page.locator('[data-output-form]').evaluate((form) => {
        const details = [...new FormData(form)].filter(([name]) => name.startsWith('detalles['));
        return Object.fromEntries(['cantidad', 'tratamiento', 'observacion'].map((field) => [
            field, details.filter(([name]) => name.endsWith(`[${field}]`)).map(([, value]) => value),
        ]));
    });
    expect(datos).toEqual({
        cantidad: ['2', '3'],
        tratamiento: ['CONSUMO', 'USO_TEMPORAL'],
        observacion: ['', 'Entregar al operador'],
    });
    await confirm.tap();
    await expect(page.getByRole('heading', { name: 'Revisa antes de descontar el stock' })).toBeVisible();
});

test.describe('Flujos de Comercial y Logística', () => {
    test.use({ credenciales: sesiones.COMERCIAL_LOGISTICA });

    test('Las ofertas y los documentos del proveedor muestran datos y acciones táctiles', async ({ page }, evidencia) => {
        await page.goto(pantallas['requerimientos-compra.comparativo'].ruta);
        const oferta = page.locator('.data-table--cards').first();
        await comprobarListado(page, 'Comparativo de ofertas', oferta, evidencia);
        const radio = oferta.getByRole('radio').first();
        await radio.check();
        await expect(radio).toBeChecked();

        await page.goto(pantallas['ordenes-compra.create'].ruta);
        await comprobarListado(page, 'Emisión de orden de compra', page.locator('.data-table--cards'), evidencia);

        await page.goto(pantallas['proveedores.show'].ruta);
        for (const seccion of ['productos', 'documentos']) {
            const panel = await abrirPestana(page, '[data-supplier-detail-tab="' + seccion + '"]');
            await comprobarListado(page, 'Proveedor, ' + seccion, panel.locator('.data-table--cards'), evidencia);
        }
    });
});

test.describe('Flujos de Contabilidad', () => {
    test.use({ credenciales: sesiones.CONTABILIDAD });

    test('El detalle de la factura y de la compra aprobada cabe en el panel', async ({ page }, evidencia) => {
        await page.goto(pantallas['facturas-proveedor.show'].ruta);
        const panel = await abrirPestana(page, '[data-supplier-invoice-tab="productos"]');
        await comprobarListado(page, 'Productos facturados', panel.locator('.data-table--cards'), evidencia);
        await expect(panel.getByText(escenario.producto, { exact: true })).toBeVisible();
        await page.goto(pantallas['solicitudes-compra.show'].ruta);
        await comprobarListado(page, 'Compra aprobada', page.locator('.data-table--cards'), evidencia);
    });
});

test.describe('Flujos de Jefatura de planta', () => {
    test.use({ credenciales: sesiones.JEFE_PLANTA });

    test('La comparación de materiales por área se lee en el ancho disponible', async ({ page }, evidencia) => {
        await page.goto(pantallas['ordenes-operacion.show'].ruta);
        const panel = await abrirPestana(page, '[data-operation-tab="materiales"]');
        const tabla = panel.locator('.operation-comparison-table');
        await comprobarListado(page, 'Comparación de materiales', tabla, evidencia);
        await expect(tabla.getByText(escenario.producto, { exact: true })).toBeVisible();
    });
});
