(() => {
    function cerrarEditor(detalle, formulario) {
        formulario?.reset();
        detalle.open = false;
    }

    const editorCuenta = document.querySelector('[data-editor-cuenta]');
    const formularioCuenta = editorCuenta?.querySelector('form');

    document.querySelectorAll('[data-cuenta-bbva]').forEach((input) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '');
        });
    });

    document.querySelector('[data-cancelar-cuenta]')?.addEventListener('click', () => {
        cerrarEditor(editorCuenta, formularioCuenta);
    });

    const editorCorreo = document.querySelector('[data-editor-correo]');
    document.querySelector('[data-cancelar-correo]')?.addEventListener('click', () => {
        cerrarEditor(editorCorreo, editorCorreo.querySelector('form'));
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
