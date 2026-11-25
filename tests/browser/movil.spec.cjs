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
