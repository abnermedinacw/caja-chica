const { ref } = Vue;

// El problema real que resuelve esto: cada "Guardar" recarga la página
// para traer el estado fresco del servidor (ids nuevos, totales
// recalculados...). Ese reflow es sutil — nada en pantalla "grita" que
// la acción terminó bien, así que un usuario que se distrajo un segundo
// puede no notar que ya se guardó. sessionStorage sobrevive al reload
// (a diferencia de una variable en memoria) así que se puede dejar el
// mensaje "para después" antes de recargar, y mostrarlo apenas la
// página nueva termina de montar Vue.
export function usarToast() {
    const toast = ref(null);
    let salioConGuardadoExitoso = false;

    (function leerToastPendiente() {
        try {
            const guardado = sessionStorage.getItem('cc_toast');
            if (guardado) {
                sessionStorage.removeItem('cc_toast');
                toast.value = JSON.parse(guardado);
                setTimeout(() => { toast.value = null; }, 5000);
            }
        } catch (e) { /* sessionStorage bloqueado (ej. navegación privada) — sin toast, no rompe nada más */ }
    })();

    function mostrarToast(mensaje, duracionMs = 4000) {
        toast.value = { mensaje };
        setTimeout(() => { toast.value = null; }, duracionMs);
    }

    function guardarYRecargar(mensaje) {
        try { sessionStorage.setItem('cc_toast', JSON.stringify({ mensaje })); } catch (e) { }
        salioConGuardadoExitoso = true;
        window.location.reload();
    }

    function salioConGuardado() { return salioConGuardadoExitoso; }

    return { toast, mostrarToast, guardarYRecargar, salioConGuardado };
}
