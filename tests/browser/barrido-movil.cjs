const { mkdir, writeFile } = require('node:fs/promises');
const { join } = require('node:path');
const { chromium, webkit } = require('playwright');
const { escribirInforme } = require('./informe-barrido.cjs');
const { sesiones, pantallas, exclusiones } = require('./.runtime/barrido.json');

const origen = 'http://127.0.0.1:8000';
const carpeta = join(__dirname, 'artifacts/barrido-movil');
const navegadores = { chromium, webkit };
const perfiles = Object.keys(navegadores).flatMap(navegador => [375, 480].map(ancho => ({ navegador, ancho })));

async function abrirPestana(page, pestana) {
    await pestana.tap();
    await page.locator('[role="tab"][aria-selected="true"]').and(pestana).waitFor();
}

const estados = {
    dashboard: [
        ['Abrir menú lateral', async page => {
            const control = page.locator('[data-sidebar-toggle]');
            await control.tap();
            await page.waitForFunction(() => document.querySelector('[data-sidebar-toggle]')?.getAttribute('aria-expanded') === 'true');
        }],
    ],
    'registro-almacen.detalle': [
        ['Resultados desplegados', async page => {
            await page.locator('#registro-consulta > summary').tap();
            await page.locator('#registro-consulta[open]').waitFor();
        }],
        ['Precios de referencia', page => abrirPestana(page, page.locator('[data-product-detail-tab="precios"]'))],
        ['Confirmación de limpiar', async page => {
            const resumen = page.locator('#registro-consulta > summary');
            if (!(await resumen.evaluate(elemento => elemento.parentElement.open))) {
                await resumen.tap();
            }

            await page.locator('button[form="registro-limpiar"]').tap();
            const dialogo = page.getByRole('dialog', { name: 'Limpiar búsqueda' });
            await dialogo.waitFor();
        }],
    ],
    'ordenes-compra.show': [
        ['Productos y entregas', page => abrirPestana(page, page.getByRole('tab', { name: /Productos y entregas/ }))],
        ['Historial', page => abrirPestana(page, page.getByRole('tab', { name: 'Historial' }))],
    ],
    'inventarios-periodicos.show': [
        ['Conteo físico', page => abrirPestana(page, page.locator('#periodic-tab-count'))],
    ],
    'inventarios-periodicos.create': [
        ['Repisa seleccionada', async page => {
            const { repisa } = require('./.runtime/escenario.json');
            await page.getByRole('searchbox', { name: 'Código de repisa' }).fill(repisa);
            await page.getByRole('checkbox', { name: new RegExp(repisa) }).check();
            await page.locator('[data-shelf-summary] summary').tap();
            await page.getByRole('button', { name: 'Quitar repisa ' + repisa }).waitFor();
        }],
    ],
};

async function abrirContexto(browser, perfil, rol) {
    const context = await browser.newContext({
        viewport: { width: perfil.ancho, height: 850 },
        isMobile: true,
        hasTouch: true,
        reducedMotion: 'reduce',
        serviceWorkers: 'block',
        acceptDownloads: false,
    });
    await context.route('**/*', route => {
        const request = route.request();
        const url = new URL(request.url());
        const permitido = url.origin === origen &&
            (request.method() === 'GET' || (request.method() === 'POST' && url.pathname === '/login'));
        return permitido ? route.continue() : route.abort();
    });

    try {
        const page = await context.newPage();
        page.setDefaultTimeout(6000);
        if (rol !== 'INVITADO') {
            const cuenta = sesiones[rol];
            await page.goto(origen + '/login');
            await page.locator('#email').fill(cuenta.email);
            await page.locator('#password').fill(cuenta.clave);
            await Promise.all([
                page.waitForURL(url => url.pathname === '/dashboard'),
                page.locator('[data-login-submit]').tap(),
            ]);
        }
        return page;
    } catch (error) {
        await context.close();
        throw error;
    }
}

async function leerAncho(page) {
    return page.evaluate(() => {
        const ventana = document.documentElement.clientWidth;
        const documento = document.documentElement.scrollWidth;
        const panelesDesbordados = Array.from(document.querySelectorAll('main .table-wrap--cards'))
            .filter(panel => panel.clientWidth > 0 && panel.scrollWidth > panel.clientWidth + 1)
            .map(panel => panel.scrollWidth - panel.clientWidth);

        // Los elementos de una tabla con su propio scroll no ensanchan la página.
        const candidatos = documento > ventana + 1 ? Array.from(document.querySelectorAll('main *'))
            .filter(elemento => {
                const rect = elemento.getBoundingClientRect();
                if (rect.width <= 0 || rect.height <= 0 || rect.right <= ventana + 1) {
                    return false;
                }

                for (let padre = elemento.parentElement; padre && padre !== document.body; padre = padre.parentElement) {
                    if (getComputedStyle(padre).overflowX !== 'visible' && padre.getBoundingClientRect().right <= ventana + 1) {
                        return false;
                    }
                }
                return true;
            })
            .slice(0, 8)
            .map(elemento => {
                const rect = elemento.getBoundingClientRect();
                const etiqueta = elemento.id ? '#' + elemento.id
                    : elemento.localName + Array.from(elemento.classList).slice(0, 3).map(clase => '.' + clase).join('');
                return etiqueta + ' llega a ' + Math.ceil(rect.right) + ' px';
            }) : [];

        return { ventana, documento, candidatos, panelesDesbordados };
    });
}

async function medir(page, ancho) {
    const inicial = await leerAncho(page);
    let medidas = inicial;
    const hallazgos = [];

    if (inicial.ventana !== ancho || inicial.documento > inicial.ventana + 1) {
        await page.evaluate(() => new Promise(resolve =>
            requestAnimationFrame(() => requestAnimationFrame(resolve))
        ));
        medidas = await leerAncho(page);

        if (medidas.ventana === ancho && medidas.documento <= medidas.ventana + 1) {
            hallazgos.push('Durante la carga el documento midió ' + inicial.documento +
                ' px y se ajustó a ' + medidas.documento + ' px tras renderizar.');
            medidas = { ...medidas, candidatos: inicial.candidatos, documentoInicial: inicial.documento };
        }
    }

    if (medidas.ventana !== ancho) {
        hallazgos.push('El ancho CSS de la ventana es ' + medidas.ventana + ' px, se esperaban ' + ancho + ' px.');
    }
    if (medidas.documento > medidas.ventana + 1) {
        hallazgos.push('Desborde horizontal de ' + (medidas.documento - medidas.ventana) + ' px.');
    }
    for (const exceso of medidas.panelesDesbordados) {
        hallazgos.push('Una tabla en tarjetas excede su panel en ' + exceso + ' px.');
    }
    return { ...medidas, hallazgos };
}

function rutaSinOrigen(url) {
    const { pathname, search } = new URL(url);
    return pathname + search;
}

async function capturar(page, perfil, rol, nombre, pantalla, estado, accion) {
    const resultado = { ...perfil, rol, nombre, ruta: pantalla.ruta, estado, hallazgos: [], candidatos: [] };
    const errores = new Set();
    const registrarError = error => errores.add('JavaScript: ' + error.message);
    const registrarRespuesta = respuesta => {
        const solicitud = respuesta.request();
        if (respuesta.status() >= 400 && solicitud.resourceType() !== 'document' &&
            respuesta.url().startsWith(origen + '/')) {
            errores.add('HTTP ' + respuesta.status() + ' al cargar ' + rutaSinOrigen(respuesta.url()) + '.');
        }
    };

    page.on('pageerror', registrarError);
    page.on('response', registrarRespuesta);
    try {
        const respuesta = await page.goto(new URL(pantalla.ruta, origen).href, { waitUntil: 'load', timeout: 15000 });
        resultado.http = respuesta?.status();
        resultado.rutaFinal = rutaSinOrigen(page.url());

        if (resultado.http !== 200 || !respuesta?.headers()['content-type']?.includes('text/html')) {
            resultado.hallazgos.push('Se esperaba una pantalla HTML con HTTP 200.');
        } else if (resultado.rutaFinal !== resultado.ruta) {
            resultado.hallazgos.push('La pantalla redirige a ' + resultado.rutaFinal + '.');
        } else {
            await page.locator('body').waitFor({ state: 'visible' });
            if (accion) {
                await accion(page);
            }
            const medidas = await medir(page, perfil.ancho);
            Object.assign(resultado, medidas);
        }
        resultado.titulo = await page.title();
    } catch (error) {
        resultado.hallazgos.push(error.message.split('\n')[0]);
    } finally {
        page.off('pageerror', registrarError);
        page.off('response', registrarRespuesta);
    }

    resultado.hallazgos.push(...errores);
    // Una captura por pantalla y perfil, más cada combinación que tenga un hallazgo.
    if (rol === pantalla.roles[0] || resultado.hallazgos.length) {
        const sufijo = (estados[nombre] ?? []).findIndex(([etiqueta]) => etiqueta === estado) + 1;
        resultado.captura = ['capturas', nombre + '-' + rol + '-' + perfil.navegador + '-' + perfil.ancho + '-' + sufijo + '.jpg'].join('/');
        try {
            await page.screenshot({ path: join(carpeta, resultado.captura), fullPage: true, type: 'jpeg', quality: 60, timeout: 8000 });
        } catch {
            delete resultado.captura;
            resultado.hallazgos.push('No se pudo guardar la captura.');
        }
    }
    return resultado;
}

async function auditarRol(browser, perfil, rol, resultados) {
    const candidatas = Object.entries(pantallas).filter(([, pantalla]) => pantalla.roles.includes(rol));
    if (!candidatas.length) {
        return;
    }

    let page;
    try {
        page = await abrirContexto(browser, perfil, rol);
    } catch (error) {
        for (const [nombre, pantalla] of candidatas) {
            resultados.push({
                ...perfil, rol, nombre, ruta: pantalla.ruta, estado: 'Apertura', candidatos: [],
                hallazgos: ['No se pudo iniciar la sesión del perfil. ' + error.message.split('\n')[0]],
            });
        }
        return;
    }

    try {
        for (const [nombre, pantalla] of candidatas) {
            const apertura = await capturar(page, perfil, rol, nombre, pantalla, 'Apertura');
            resultados.push(apertura);
            if (rol === pantalla.roles[0] && apertura.http === 200 && apertura.rutaFinal === apertura.ruta) {
                for (const [estado, accion] of estados[nombre] ?? []) {
                    resultados.push(await capturar(page, perfil, rol, nombre, pantalla, estado, accion));
                }
            }
        }
    } finally {
        await page.context().close();
    }
}

async function main() {
    await mkdir(join(carpeta, 'capturas'), { recursive: true });
    const resultados = [];
    const roles = ['INVITADO', ...Object.keys(sesiones)];
    for (const perfil of perfiles) {
        const executablePath = perfil.navegador === 'chromium' ? process.env.CHROME_PATH : undefined;
        let browser;
        try {
            browser = await navegadores[perfil.navegador].launch({ executablePath });
        } catch (error) {
            resultados.push({
                ...perfil, rol: '—', nombre: 'Inicio del navegador', estado: 'Apertura', candidatos: [],
                hallazgos: [error.message.split('\n')[0]],
            });
            continue;
        }
        try {
            for (const rol of roles) {
                const antes = resultados.length;
                await auditarRol(browser, perfil, rol, resultados);
                console.log(perfil.navegador + ' ' + perfil.ancho + ' px · ' + rol + ' · ' + (resultados.length - antes) + ' comprobaciones');
            }
        } finally {
            await browser.close();
            await writeFile(join(carpeta, 'resultados.json'), JSON.stringify({ resultados, exclusiones }, null, 2));
        }
    }
    process.exitCode = await escribirInforme(carpeta, pantallas, exclusiones, resultados) ? 1 : 0;
}

main().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
