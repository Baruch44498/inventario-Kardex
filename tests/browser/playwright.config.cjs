const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
    testMatch: 'movil.spec.cjs',
    workers: 1,
    outputDir: 'artifacts/playwright-results',
    reporter: [['list'], ['html', { outputFolder: 'artifacts/playwright', open: 'never' }]],
    use: {
        baseURL: 'http://127.0.0.1:8000',
        isMobile: true,
        hasTouch: true,
        actionTimeout: 8000,
        navigationTimeout: 15000,
        screenshot: 'only-on-failure',
    },
    projects: ['chromium', 'webkit'].flatMap(browserName => [375, 480].map(width => ({
        name: `${browserName}-${width}px`,
        use: {
            browserName,
            viewport: { width, height: 850 },
            ...(browserName === 'chromium' && process.env.CHROME_PATH
                ? { launchOptions: { executablePath: process.env.CHROME_PATH } }
                : {}),
        },
    }))),
});
