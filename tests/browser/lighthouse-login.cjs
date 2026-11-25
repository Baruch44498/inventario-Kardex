const { email, clave } = require('./.runtime/escenario.json');

module.exports = async (browser, { url }) => {
    const page = await browser.newPage();

    try {
        await page.goto(new URL('/login', url).href, { waitUntil: 'domcontentloaded' });
        await page.type('#email', email);
        await page.type('#password', clave);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
            page.click('[data-login-submit]'),
        ]);
        await page.goto(url, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#registro-producto-titulo', { timeout: 10000 });
    } finally {
        await page.close();
    }
};
