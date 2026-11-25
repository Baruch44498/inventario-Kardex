const { expect, test } = require('@playwright/test');
const escenario = require('./.runtime/escenario.json');

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

test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill(escenario.email);
    await page.locator('#password').fill(escenario.clave);
    await page.locator('[data-login-submit]').tap();
    await expect(page).not.toHaveURL(/\/login(?:[?#]|$)/);
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
    await comprobarAncho(page, 'Orden de compra, apertura', evidencia);
    await page.getByRole('tab', { name: 'Resumen', exact: true }).tap();
    await expect(page.locator('#purchase-order-panel-resumen')).toBeVisible();
    await comprobarAncho(page, 'Orden de compra, resumen', evidencia);
    const productos = page.getByRole('tab', { name: /Productos y entregas/ });
    await productos.tap();
    await expect(productos).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('#purchase-order-panel-productos')).toBeVisible();
    await comprobarAncho(page, 'Orden de compra, productos y entregas', evidencia);
    const desplazamiento = await page.getByRole('region', { name: 'Productos y avance de recepción' }).evaluate(contenedor => {
        contenedor.scrollLeft = contenedor.scrollWidth;
        return contenedor.scrollLeft;
    });
    expect(desplazamiento).toBeGreaterThan(0);
    await page.getByRole('tab', { name: 'Historial' }).tap();
    await expect(page.locator('#purchase-order-panel-historial')).toBeVisible();
    await page.getByRole('link', { name: 'Registrar recepción' }).click({ trial: true });
    await comprobarAncho(page, 'Orden de compra, historial', evidencia);
});
