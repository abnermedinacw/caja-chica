import { formatearSoles } from "./utils/formatos.js";
const { createApp, ref, computed, watch } = Vue;

createApp({
    setup() {
        const datos = window.DATOS_INICIALES || { fondos: [], empresas: [] };

        const empresaId = ref('');
        const fondoId = ref('');
        const error = ref(null);
        const guardando = ref(false);

        // Solo se muestran los fondos de la empresa elegida — un fondo de
        // otra empresa del grupo no aplica a este Resumen aunque el
        // trabajador lo tenga asignado.
        const fondosDeEmpresa = computed(() =>
            datos.fondos.filter(f => String(f.empresa_id) === String(empresaId.value))
        );

        // Al cambiar de empresa: si tiene fondos asignados, pre-selecciona el
        // primero (el usuario igual puede cambiarlo o elegir "Sin fondo");
        // si no tiene, limpia cualquier fondo de la empresa anterior.
        watch(empresaId, () => {
            const fondos = fondosDeEmpresa.value;
            fondoId.value = fondos.length > 0 ? fondos[0].fondo_id : '';
        });

        const fondoSeleccionado = computed(() =>
            datos.fondos.find(f => String(f.fondo_id) === String(fondoId.value))
        );

        async function crear() {
            error.value = null;
            guardando.value = true;

            const form = document.querySelector('#formResumen');
            const datosFormulario = new FormData(form);

            try {
                const respuesta = await fetch(form.action, { method: 'POST', body: datosFormulario });
                const resultado = await respuesta.json();

                if (resultado.ok) {
                    window.location.href = resultado.redirect;
                    return;
                }
                error.value = resultado.error;
            } catch (e) {
                error.value = 'No se pudo conectar con el servidor. Intenta de nuevo.';
            } finally {
                guardando.value = false;
            }
        }

        return { datos, empresaId, fondoId, fondosDeEmpresa, fondoSeleccionado, error, guardando, formatearSoles, crear };
    },
}).mount('#app');