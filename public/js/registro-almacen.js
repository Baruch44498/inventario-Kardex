(() => {
    const registro = document.querySelector('[data-registro-almacen]');
    const confirmacion = registro?.querySelector('[data-registro-confirmar-salida]');
    if (!confirmacion) return;

    const resultados = registro.querySelector('[data-registro-resultados]');
    const busqueda = registro.querySelector('#registro-busqueda');
    const tabConError = registro.querySelector('[data-registro-tab-error]')?.dataset.registroTabError;

    // El formulario devuelto por el servidor tiene prioridad sobre el fragmento.
    if (tabConError) {
        window.history.replaceState(window.history.state, '', `#${tabConError}`);
    }

    const mostrarResultadosPaginados = () => {
        if (resultados && window.location.hash === '#resultados') resultados.open = true;
    };

    mostrarResultadosPaginados();
    window.addEventListener('hashchange', mostrarResultadosPaginados);

    registro.querySelector('[data-registro-cambiar]').addEventListener('click', () => {
        if (resultados) resultados.open = true;
        busqueda.focus();
        busqueda.select();
    });

    registro.querySelectorAll('[data-registro-abrir-tab]').forEach((boton) => {
        const tab = document.getElementById(`registro-tab-${boton.dataset.registroAbrirTab}`);
        boton.addEventListener('click', () => {
            tab?.click();
            tab?.focus();
        });
    });

    const serializar = (formulario) => JSON.stringify([
        [...new FormData(formulario)],
        [...formulario.querySelectorAll('[data-remote-combobox-search]')].map((campo) => campo.value),
    ]);
    const estados = new Map(
        [...registro.querySelectorAll('[data-dirty-form]')].map((formulario) => [formulario, serializar(formulario)])
    );
    const hayCambios = (excepto) => [...estados].some(([formulario, inicial]) =>
        formulario !== excepto && (formulario.hasAttribute('data-registro-draft-restored') || serializar(formulario) !== inicial)
    );

    let continuar;
    const confirmarSalida = (accion) => {
        continuar = accion;
        confirmacion.requestSubmit();
    };

    // El modal corporativo reenvía el submit después de confirmar.
    confirmacion.addEventListener('submit', (evento) => {
        if (evento.defaultPrevented) return;

        evento.preventDefault();
        const accion = continuar;
        continuar = null;
        accion?.();
    });

    document.addEventListener('click', (evento) => {
        if (evento.defaultPrevented || evento.button !== 0 || evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey) return;

        const enlace = evento.target.closest('a[href]');
        if (!enlace || (enlace.target && enlace.target !== '_self') || enlace.hasAttribute('download')) return;

        const destino = new URL(enlace.href);
        if (!['http:', 'https:'].includes(destino.protocol)) return;
        const actual = window.location;
        const mismoDocumento = destino.origin === actual.origin && destino.pathname === actual.pathname
            && destino.search === actual.search && enlace.getAttribute('href').includes('#');
        if (mismoDocumento || !hayCambios()) return;

        evento.preventDefault();
        evento.stopImmediatePropagation();
        confirmarSalida(() => window.location.assign(destino.href));
    }, true);

    const reenvios = new WeakSet();
    document.addEventListener('submit', (evento) => {
        const formulario = evento.target;
        // Limpiar ya exige su propia confirmación, incluso con formularios intactos.
        if (formulario === confirmacion || formulario.id === 'registro-limpiar' || formulario.dataset.confirmBypass === 'true') return;
        if (reenvios.delete(formulario) || !hayCambios(formulario)) return;

        evento.preventDefault();
        evento.stopImmediatePropagation();
        confirmarSalida(() => {
            reenvios.add(formulario);
            formulario.requestSubmit(evento.submitter ?? undefined);
        });
    }, true);
})();
