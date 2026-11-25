const { rutas } = require('./.runtime/escenario.json');
const perfil = process.env.UI_PERFIL || 'mobile';

module.exports = {
    ci: {
        collect: {
            url: [`http://127.0.0.1:8000${rutas.registroDetalle}`],
            numberOfRuns: 3,
            puppeteerScript: './lighthouse-login.cjs',
            settings: {
                ...(perfil === 'desktop' ? { preset: 'desktop' } : {}),
                onlyCategories: ['performance', 'accessibility', 'best-practices'],
                disableStorageReset: true,
            },
        },
        upload: {
            target: 'filesystem',
            outputDir: `./artifacts/lighthouse-${perfil}`,
        },
    },
};
