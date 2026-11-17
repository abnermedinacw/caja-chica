import { enviarFormulario } from './utils/api.js';
import { formatearFecha, formatearSoles, numeroDocumento } from './utils/formatos.js';

const { createApp, ref, computed } = Vue;

createApp({
    setup() {
        const datos = window.DATOS_INICIALES || { baseUrl: '', resumenes: [], empresas: [], paginacion: {} };
        const resumenes = ref(datos.resumenes || []);

        const filtroBuscar = ref(datos.paginacion?.buscar || '');
        const filtroEmpresa = ref(datos.paginacion?.empresa_id ? String(datos.paginacion.empresa_id) : '');
        const filtroEstado = ref(datos.paginacion?.estado || '');
        const porPagina = ref(Number(datos.paginacion?.por_pagina || 25));

        const hayFiltrosActivos = computed(() =>
            Boolean(filtroBuscar.value.trim() || filtroEmpresa.value || filtroEstado.value)
        );
        const mostrarFiltros = ref(hayFiltrosActivos.value);

        const refModalAnular = ref(null);
        const resumenSeleccionado = ref(null);
        const motivo = ref('');
        const errorAnulacion = ref(null);
        const anulando = ref(false);
        const mensajeExito = ref(null);

        function abrirModalAnular(resumen) {
            resumenSeleccionado.value = resumen;
            motivo.value = '';
            errorAnulacion.value = null;
            refModalAnular.value?.showModal();
        }

        function cerrarModal() {
            if (anulando.value) return;
            refModalAnular.value?.close();
            resumenSeleccionado.value = null;
            motivo.value = '';
            errorAnulacion.value = null;
        }

        async function confirmarAnulacion() {
            if (!resumenSeleccionado.value || anulando.value) return;
            errorAnulacion.value = null;
            anulando.value = true;

            const resultado = await enviarFormulario(datos.baseUrl + '/anular-resumen', {
                resumen_id: resumenSeleccionado.value.id,
                motivo: motivo.value.trim(),
            });

            anulando.value = false;
            if (!resultado.ok) {
                errorAnulacion.value = resultado.error || 'No se pudo anular el resumen.';
                return;
            }

            const num = numeroDocumento(resumenSeleccionado.value.numero);
            const idx = resumenes.value.findIndex(r => Number(r.id) === Number(resumenSeleccionado.value.id));
            if (idx !== -1) {
                resumenes.value[idx] = {
                    ...resumenes.value[idx],
                    estado_general: 'ANULADO',
                    nivel_actual_nombre: null,
                };
            }

            cerrarModal();
            mensajeExito.value = `Resumen N° ${num} anulado correctamente.`;
        }

        return {
            datos,
            resumenes,
            mostrarFiltros,
            hayFiltrosActivos,
            filtroBuscar,
            filtroEmpresa,
            filtroEstado,
            porPagina,
            refModalAnular,
            resumenSeleccionado,
            motivo,
            errorAnulacion,
            anulando,
            mensajeExito,
            abrirModalAnular,
            cerrarModal,
            confirmarAnulacion,
            formatearFecha,
            formatearSoles,
            numeroDocumento,
        };
    },
}).mount('#app');
