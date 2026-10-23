document.addEventListener('DOMContentLoaded', () => {
    const config = window.CONFIG_SESION;
    const dialogo = document.getElementById('dialogo-sesion-inactiva');
    const botonContinuar = document.getElementById('boton-continuar-sesion');
    const textoTiempo = document.getElementById('tiempo-restante-sesion');

    if (!config || !dialogo || !botonContinuar || !textoTiempo) return;

    const limiteMs = config.limiteSegundos * 1000;
    const avisoMs = config.avisoSegundos * 1000;
    const frecuenciaPingMs = 60 * 1000;

    let ultimaActividad = Date.now();
    let ultimoPing = Date.now();
    let renovando = false;

    function url(ruta) {
        return `${config.baseUrl}${ruta}`;
    }

    function cerrarPorInactividad() {
        window.location.replace(url('/logout?motivo=inactividad'));
    }

    function formatearTiempo(milisegundos) {
        const segundos = Math.max(0, Math.ceil(milisegundos / 1000));

        const minutos = Math.floor(segundos / 60);
        const resto = String(segundos % 60).padStart(2, '0');

        return `${minutos}:${resto}`;
    }

    async function renovarSesion() {
        if (renovando) return false;
        renovando = true;

        try {
            const respuesta = await fetch(
                url('/renovar-sesion'),
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }
            );
            if (respuesta.status === 401) {
                cerrarPorInactividad();
                return false;
            }

            if (!respuesta.ok) return false;

            const resultado = await respuesta.json();

            if (!resultado.ok) {
                cerrarPorInactividad();
                return false;
            }

            ultimoPing = Date.now();
            return true;
        } catch (error) {
            return false;
        } finally {
            renovando = false;
        }
    }

    async function registrarActividad() {
        if (dialogo.open) return;

        ultimaActividad = Date.now();

        if (Date.now() - ultimoPing >= frecuenciaPingMs) {
            await renovarSesion();
        }
    }

    function revisarInactividad() {
        const transcurrido = Date.now() - ultimaActividad;
        const restante = limiteMs - transcurrido;

        if (restante <= 0) {
            cerrarPorInactividad();
            return;
        }

        if (restante <= avisoMs) {
            textoTiempo.textContent = formatearTiempo(restante);

            if (!dialogo.open) dialogo.showModal();
            return;
        }

        if (dialogo.open) dialogo.close();
    }

    botonContinuar.addEventListener('click', async () => {
        botonContinuar.disabled = true;
        botonContinuar.textContent = 'Renovando...';

        const renovada = await renovarSesion();

        if (renovada) {
            ultimaActividad = Date.now();
            dialogo.close();
        }

        botonContinuar.disabled = false;
        botonContinuar.textContent = 'Seguir trabajando';
    });

    dialogo.addEventListener('cancel', (evento) => {
        evento.preventDefault();
    });

    const eventosActividad = [
        'pointerdown',
        'keydown',
        'input',
        'scroll',
        'touchstart',
    ];

    eventosActividad.forEach((nombreEvento) => {
        document.addEventListener(nombreEvento, registrarActividad, {passive: true});
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) revisarInactividad();
    });

    setInterval(revisarInactividad, 1000);
})