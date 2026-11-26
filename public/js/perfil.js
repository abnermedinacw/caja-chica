(() => {
    function cerrarEditor(detalle, formulario) {
        formulario?.reset();
        detalle.open = false;
    }

    document.querySelectorAll('[data-solo-digitos], [data-cuenta-bbva]').forEach((input) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '');
        });
    });

    const editorCuenta = document.querySelector('[data-editor-cuenta]');
    document.querySelector('[data-cancelar-cuenta]')?.addEventListener('click', () => {
        cerrarEditor(editorCuenta, editorCuenta?.querySelector('form'));
    });

    const editorCuentaBcp = document.querySelector('[data-editor-cuenta-bcp]');
    const selectTipoBcp = document.querySelector('[data-tipo-cuenta-bcp]');
    const inputsBcp = document.querySelectorAll('[data-input-bcp]');

    function sincronizarLongitudBcp() {
        if (!selectTipoBcp) return;
        const max = selectTipoBcp.value === 'C' ? 13 : 14;
        const etiqueta = selectTipoBcp.value === 'C' ? '13 dígitos' : '14 dígitos';
        inputsBcp.forEach((input) => {
            input.maxLength = max;
            input.placeholder = etiqueta;
            if (input.value.length > max) {
                input.value = input.value.slice(0, max);
            }
        });
    }

    selectTipoBcp?.addEventListener('change', sincronizarLongitudBcp);
    sincronizarLongitudBcp();

    document.querySelector('[data-cancelar-cuenta-bcp]')?.addEventListener('click', () => {
        cerrarEditor(editorCuentaBcp, editorCuentaBcp?.querySelector('form'));
        sincronizarLongitudBcp();
    });

    const dialogoCambioBanco = document.getElementById('dialogoCambioBanco');
    const textoNuevoBanco = document.getElementById('textoNuevoBanco');
    const inputNuevoBanco = document.getElementById('inputNuevoBanco');

    function abrirCambioBanco(banco) {
        if (!dialogoCambioBanco || !textoNuevoBanco || !inputNuevoBanco) return;
        textoNuevoBanco.textContent = banco;
        inputNuevoBanco.value = banco;
        dialogoCambioBanco.showModal();
    }

    document.querySelectorAll('[data-cerrar-cambio-banco]').forEach((boton) => {
        boton.addEventListener('click', () => dialogoCambioBanco?.close());
    });

    document.querySelectorAll('[data-seleccionar-banco]').forEach((tarjeta) => {
        const banco = tarjeta.dataset.seleccionarBanco;
        tarjeta.addEventListener('click', (evento) => {
            // No disparar el cambio de banco al interactuar con el editor de cuenta
            if (evento.target.closest('details, summary, form, button, input, select, label, a')) {
                return;
            }
            abrirCambioBanco(banco);
        });

        tarjeta.addEventListener('keydown', (evento) => {
            if (evento.target !== tarjeta) return;
            if (evento.key === 'Enter' || evento.key === ' ') {
                evento.preventDefault();
                abrirCambioBanco(banco);
            }
        });
    });

    const editorCorreo = document.querySelector('[data-editor-correo]');
    document.querySelector('[data-cancelar-correo]')?.addEventListener('click', () => {
        cerrarEditor(editorCorreo, editorCorreo?.querySelector('form'));
    });

    const editorFirma = document.querySelector('[data-editor-firma]');
    const formularioFirma = editorFirma?.querySelector('form');
    const inputFirma = document.getElementById('nuevaFirma');
    const seleccion = document.getElementById('firmaSeleccionada');
    const nombreArchivo = document.getElementById('nombreNuevaFirma');

    function limpiarFirma() {
        if (!inputFirma || !seleccion || !nombreArchivo) return;
        inputFirma.value = '';
        nombreArchivo.textContent = '';
        seleccion.hidden = true;
    }

    inputFirma?.addEventListener('change', () => {
        const archivo = inputFirma.files?.[0];
        if (!archivo) {
            limpiarFirma();
            return;
        }
        nombreArchivo.textContent = archivo.name;
        seleccion.hidden = false;
    });

    document.getElementById('limpiarNuevaFirma')?.addEventListener('click', limpiarFirma);
    document.getElementById('cancelarNuevaFirma')?.addEventListener('click', () => {
        limpiarFirma();
        cerrarEditor(editorFirma, formularioFirma);
    });
})();
