import { formatearFecha, formatearSoles, numeroDocumento } from './utils/formatos.js';
import { coincideEnAlguno, coincideExacto, dentroDeRango } from './utils/filtros.js';

const { createApp, ref, computed } = Vue;
createApp({
    setup() {
        const datos = window.DATOS_INICIALES || { baseUrl: '', resumenes: [] };
        const resumenes = datos.resumenes.map(r => ({ ...r, numero_formateado: numeroDocumento(r.numero) }));
        const empresas = computed(() => [...new Set(resumenes.map(r => r.empresa))].sort());

        const mostrarFiltros = ref(false);
        const filtroNumero = ref('');
        const filtroEstado = ref('');
        const filtroDesde = ref('');
        const filtroHasta = ref('');
        const filtroEmpresa = ref('');

        const hayFiltrosActivos = computed(() =>
            !!(filtroNumero.value || filtroEstado.value || filtroDesde.value || filtroHasta.value || filtroEmpresa.value)
        );
        function limpiarFiltros() {
            filtroNumero.value = '';
            filtroEstado.value = '';
            filtroDesde.value = '';
            filtroHasta.value = '';
            filtroEmpresa.value = '';
        }

        const resumenesFiltrados = computed(() => resumenes.filter(r =>
            coincideEnAlguno(r, ['numero', 'numero_formateado'], filtroNumero.value) &&
            coincideExacto(r.estado_general, filtroEstado.value) &&
            coincideExacto(r.empresa, filtroEmpresa.value) &&
            dentroDeRango(r.fecha_emision, filtroDesde.value, filtroHasta.value)
        ));

        return {
            datos, empresas, resumenes, resumenesFiltrados,
            mostrarFiltros, hayFiltrosActivos, limpiarFiltros,
            filtroNumero, filtroEstado, filtroDesde, filtroHasta, filtroEmpresa,
            formatearFecha, formatearSoles,
        };
    }
}).mount('#app');
