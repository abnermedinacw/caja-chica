const { ref, watch } = Vue;

export function usarModales() {
    const dialogoConfirmar = ref({ abierto: false, titulo: '', mensaje: '', textoConfirmar: 'Confirmar', tipo: 'normal', accion: null });
    const refDialogoConfirmar = ref(null);

    function pedirConfirmacion({ titulo, mensaje, textoConfirmar = 'Confirmar', tipo = 'normal', accion }) {
        dialogoConfirmar.value = { abierto: true, titulo, mensaje, textoConfirmar, tipo, accion };
    }
    function confirmarAccion() {
        if (!dialogoConfirmar.value.abierto) return;
        const accion = dialogoConfirmar.value.accion;
        dialogoConfirmar.value.accion = null;
        dialogoConfirmar.value.abierto = false;
        if (accion) accion();
    }
    function cancelarConfirmacion() { dialogoConfirmar.value.abierto = false; }

    const dialogoAviso = ref({ abierto: false, titulo: 'Aviso', mensaje: '' });
    const refDialogoAviso = ref(null);

    function mostrarAviso(mensaje, titulo = 'Aviso') {
        dialogoAviso.value = { abierto: true, titulo, mensaje };
    }
    function cerrarAviso() { dialogoAviso.value.abierto = false; }

    watch(() => dialogoConfirmar.value.abierto, (abierto) => { abierto ? refDialogoConfirmar.value?.showModal() : refDialogoConfirmar.value?.close(); });
    watch(() => dialogoAviso.value.abierto, (abierto) => { abierto ? refDialogoAviso.value?.showModal() : refDialogoAviso.value?.close(); });

    return {
        dialogoConfirmar, refDialogoConfirmar, pedirConfirmacion, confirmarAccion, cancelarConfirmacion,
        dialogoAviso, refDialogoAviso, mostrarAviso, cerrarAviso,
    };
}
