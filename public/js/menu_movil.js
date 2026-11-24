(() => {
    const botonAbrir = document.getElementById('botonAbrirMenu');
    const botonCerrar = document.getElementById('botonCerrarMenu');
    const barraLateral = document.getElementById('barraLateral');
    const backdrop = document.getElementById('backdropMenu');

    if (!botonAbrir || !barraLateral || !backdrop) return;

    const claseAbierto = 'menu-movil-abierto';
    const mediaDesktop = window.matchMedia('(min-width: 901px)');
    let temporizadorOcultar = null;

    const estaAbierto = () => document.body.classList.contains(claseAbierto);

    const abrirMenu = () => {
        if (mediaDesktop.matches) return;
        if (temporizadorOcultar) {
            clearTimeout(temporizadorOcultar);
            temporizadorOcultar = null;
        }
        backdrop.hidden = false;
        // Forzar un frame antes de activar la clase para que anime la opacidad del backdrop.
        requestAnimationFrame(() => {
            document.body.classList.add(claseAbierto);
            botonAbrir.setAttribute('aria-expanded', 'true');
            if (botonCerrar) botonCerrar.focus({ preventScroll: true });
        });
    };

    const cerrarMenu = ({ devolverFoco = false, inmediato = false } = {}) => {
        if (temporizadorOcultar) {
            clearTimeout(temporizadorOcultar);
            temporizadorOcultar = null;
        }
        const estabaAbierto = estaAbierto();
        document.body.classList.remove(claseAbierto);
        botonAbrir.setAttribute('aria-expanded', 'false');

        if (inmediato) {
            backdrop.hidden = true;
        } else if (estabaAbierto) {
            temporizadorOcultar = setTimeout(() => {
                if (!estaAbierto()) backdrop.hidden = true;
                temporizadorOcultar = null;
            }, 230);
        } else {
            backdrop.hidden = true;
        }

        if (devolverFoco && estabaAbierto) {
            botonAbrir.focus({ preventScroll: true });
        }
    };

    botonAbrir.addEventListener('click', () => {
        if (estaAbierto()) {
            cerrarMenu({ devolverFoco: true });
        } else {
            abrirMenu();
        }
    });

    if (botonCerrar) {
        botonCerrar.addEventListener('click', () => cerrarMenu({ devolverFoco: true }));
    }

    backdrop.addEventListener('click', () => cerrarMenu({ devolverFoco: true }));

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && estaAbierto()) {
            cerrarMenu({ devolverFoco: true });
        }
    });

    barraLateral.addEventListener('click', (evento) => {
        if (evento.target.closest('a[href]')) {
            cerrarMenu({ inmediato: true });
        }
    });

    const manejarCambioPantalla = (evento) => {
        if (evento.matches) {
            cerrarMenu({ inmediato: true });
        }
    };

    if (typeof mediaDesktop.addEventListener === 'function') {
        mediaDesktop.addEventListener('change', manejarCambioPantalla);
    } else if (typeof mediaDesktop.addListener === 'function') {
        mediaDesktop.addListener(manejarCambioPantalla);
    }

    window.addEventListener('pageshow', (evento) => {
        if (evento.persisted) {
            cerrarMenu({ inmediato: true });
        }
    });
})();
