import { formatearFecha, formatearSoles, numeroDocumento } from './utils/formatos.js';
import { coincideEnAlguno, coincideExacto, dentroDeRango } from './utils/filtros.js';

const { createApp, ref, computed } = Vue;

createApp({
    setup() {
        const datos = window.DATOS_INICIALES || { baseUrl: '', resumenes: [] };
        const resumenes = datos.resumenes.map(r => ({ ...r, numero_formateado: numeroDocumento(r.numero) }));

        const mostrarFiltros = ref(false);
        const filtroTexto = ref('');
        const filtroEmpresa = ref('');
        const filtroAccion = ref('');
        const filtroEstado = ref('');
        const filtroDesde = ref('');
        const filtroHasta = ref('');

        const empresasEnLista = computed(() => [...new Set(resumenes.map(r => r.empresa))].sort());

        const hayFiltrosActivos = computed(() =>
            !!(filtroTexto.value || filtroEmpresa.value || filtroAccion.value || filtroEstado.value || filtroDesde.value || filtroHasta.value)
        );
        function limpiarFiltros() {
            filtroTexto.value = '';
            filtroEmpresa.value = '';
            filtroAccion.value = '';
            filtroEstado.value = '';
            filtroDesde.value = '';
            filtroHasta.value = '';
        }

        const resumenesFiltrados = computed(() => resumenes.filter(r =>
            coincideEnAlguno(r, ['numero', 'numero_formateado', 'solicitante'], filtroTexto.value) &&
            coincideExacto(r.empresa, filtroEmpresa.value) &&
            coincideExacto(r.accion, filtroAccion.value) &&
            coincideExacto(r.estado_general, filtroEstado.value) &&
            dentroDeRango(r.fecha_accion, filtroDesde.value, filtroHasta.value)
        ));

        return {
            datos, resumenes, resumenesFiltrados,
            mostrarFiltros, hayFiltrosActivos, limpiarFiltros,
            filtroTexto, filtroEmpresa, filtroAccion, filtroEstado, filtroDesde, filtroHasta, empresasEnLista,
            formatearFecha, formatearSoles,
        };
    },
}).mount('#app');
