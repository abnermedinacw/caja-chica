const { ref } = Vue;
import { enviarFormulario } from '../utils/api.js';

// Edición de cabecera (periodo / fecha de emisión) del Resumen.
export function usarCabecera(datos, resumen, { guardarYRecargar, pedirConfirmacion }) {
    const cabeceraEditando = ref(false);
    const guardandoCabecera = ref(false);
    const errorCabecera = ref(null);
    const cabeceraForm = ref({ periodo_inicio: '', periodo_fin: '', fecha_emision: '' });

    function iniciarEdicionCabecera() {
        cabeceraForm.value = {
            periodo_inicio: resumen.periodo_inicio,
            periodo_fin: resumen.periodo_fin,
            fecha_emision: resumen.fecha_emision,
        };
        errorCabecera.value = null;
        cabeceraEditando.value = true;
    }

    function cerrarEdicionCabecera() {
        cabeceraEditando.value = false;
        errorCabecera.value = null;
    }

    function cancelarEdicionCabecera() {
        const cambio = cabeceraForm.value.periodo_inicio !== resumen.periodo_inicio
            || cabeceraForm.value.periodo_fin !== resumen.periodo_fin
            || cabeceraForm.value.fecha_emision !== resumen.fecha_emision;
        if (!cambio) {
            cerrarEdicionCabecera();
            return;
        }
        pedirConfirmacion({
            titulo: 'Descartar cambios de cabecera',
            mensaje: 'Los cambios de periodo y fecha se perderán.',
            textoConfirmar: 'Descartar',
            tipo: 'peligro',
            accion: cerrarEdicionCabecera,
        });
    }

    async function guardarCabecera() {
        errorCabecera.value = null;
        guardandoCabecera.value = true;
        const resultado = await enviarFormulario(datos.baseUrl + '/editar-cabecera', {
            resumen_id: datos.resumenId,
            ...cabeceraForm.value,
        });
        guardandoCabecera.value = false;
        if (resultado.ok) { guardarYRecargar('Cabecera actualizada.'); return; }
        errorCabecera.value = resultado.error;
    }

    return {
        cabeceraEditando, guardandoCabecera, errorCabecera, cabeceraForm,
        iniciarEdicionCabecera, cancelarEdicionCabecera, guardarCabecera,
    };
}
