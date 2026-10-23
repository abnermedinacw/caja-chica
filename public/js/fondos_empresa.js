import { formatearFecha, formatearSoles } from './utils/formatos.js';
import { coincideEnAlguno, coincideExacto, dentroDeRango } from './utils/filtros.js';
import { descargarTxtDesdeRespuesta } from './utils/descargar_txt.js';

const { createApp, ref, computed, watch } = Vue;

createApp({
    setup() {
        const datos = window.DATOS_INICIALES || { baseUrl: '', fondos: [], empresas: [], trabajadores: [], puedeAsignar: false };
        const hoy = new Date().toISOString().slice(0, 10);

        // Filtros de la lista
        const mostrarFiltros = ref(false);
        const filtroTexto = ref('');
        const filtroEmpresa = ref('');
        const filtroTipo = ref('');
        const filtroEstado = ref('');
        const filtroDesde = ref('');
        const filtroHasta = ref('');

        const hayFiltrosActivos = computed(() =>
            !!(filtroTexto.value || filtroEmpresa.value || filtroTipo.value || filtroEstado.value || filtroDesde.value || filtroHasta.value)
        );
        function limpiarFiltros() {
            filtroTexto.value = '';
            filtroEmpresa.value = '';
            filtroTipo.value = '';
            filtroEstado.value = '';
            filtroDesde.value = '';
            filtroHasta.value = '';
        }

        // Para el <select> de empresa: solo las que de verdad aparecen en
        // la lista de fondos, no todas las empresas del sistema.
        const empresasEnLista = computed(() => [...new Set(datos.fondos.map(f => f.empresa))].sort());

        const fondosFiltrados = computed(() => datos.fondos.filter(f =>
            coincideEnAlguno(f, ['dni', 'trabajador'], filtroTexto.value) &&
            coincideExacto(f.empresa, filtroEmpresa.value) &&
            coincideExacto(f.tipo, filtroTipo.value) &&
            coincideExacto(f.estado, filtroEstado.value) &&
            dentroDeRango(f.fecha_asignacion, filtroDesde.value, filtroHasta.value)
        ));

        // ---------- Modal "Nuevo Fondo" (solo si puedeAsignar) ----------
        const mostrarModalAsignar = ref(false);
        const refModalAsignar = ref(null);
        watch(mostrarModalAsignar, (abierto) => { abierto ? refModalAsignar.value?.showModal() : refModalAsignar.value?.close(); });

        const form = ref({
            empresa_id: '',
            trabajador_id: '',
            tipo: '',
            monto_asignado: '',
            fecha_asignacion: hoy,
            generar_txt: true
        });
        const error = ref(null);
        const guardando = ref(false);
        const fondoCreadoId = ref(null);

        function quitarTrabajadorSeleccionado() {
            form.value.trabajador_id = '';
            busquedaTrabajador.value = '';
            mostrarSugerencias.value = false;
        }

        function cerrarModalAsignar() {
            mostrarModalAsignar.value = false;
            error.value = null;
            fondoCreadoId.value = null;
            form.value = { empresa_id: '', trabajador_id: '', tipo: '', monto_asignado: '', fecha_asignacion: hoy, generar_txt: true};
            busquedaTrabajador.value = '';
        }

        // Autocompletar de trabajador.
        const busquedaTrabajador = ref('');
        const mostrarSugerencias = ref(false);
        const trabajadorSeleccionado = computed(() => datos.trabajadores.find(t => String(t.id) === String(form.value.trabajador_id)));

        const sugerenciasTrabajador = computed(() => {
            const termino = busquedaTrabajador.value.trim().toLowerCase();
            if (!termino) return [];
            return datos.trabajadores
                .filter(t => t.dni.includes(termino) || t.nombres.toLowerCase().includes(termino))
                .slice(0, 8);
        });

        function elegirTrabajador(t) {
            form.value.trabajador_id = t.id;
            busquedaTrabajador.value = '';
            mostrarSugerencias.value = false;
        }

        function ocultarSugerenciasConRetraso() {
            setTimeout(() => { mostrarSugerencias.value = false; }, 150);
        }

        async function asignar() {
            error.value = null;
            guardando.value = true;

            try {
                // Si ya se creó el fondo, no crea otro, solo reintenta el txt
                if (fondoCreadoId.value) {
                    const generado = await descargarTxtFondo(fondoCreadoId.value);
                    if (generado) window.location.reload();
                    return;
                }
                const formData = new FormData();
                Object.entries(form.value).forEach(([clave, valor]) => {
                    formData.append(clave, valor);
                });

                const respuesta = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                });

                const resultado = await respuesta.json();

                if (!resultado.ok) {
                    error.value = resultado.error;
                    return;
                }

                fondoCreadoId.value = Number(resultado.id);

                if (!form.value.generar_txt) {
                    window.location.reload();
                    return;
                }

                const generado = await descargarTxtFondo(fondoCreadoId.value);
                if (generado) window.location.reload();
            } catch (e) {
                error.value = 'No se pudo conectar con el servidor.';
            } finally {
                guardando.value = false;
            }
        }

        async function descargarTxtFondo(fondoId) {
            const formData = new FormData();

            formData.append('empresa_id', form.value.empresa_id);
            formData.append('fondos[]', fondoId);

            const respuesta = await fetch(
                `${datos.baseUrl}/generar-txt-fondos`,
                {
                    method: 'POST',
                    body: formData
                }
            );

            if (!respuesta.ok) {
                const resultado = await respuesta.json();
                error.value = resultado.error || 'El fondo fue creado, pero no se pudo generar el TXT.';
                return false;
            }

            await descargarTxtDesdeRespuesta(respuesta, 'DESEMBOLSO_FONDO_BBVA.txt');

            return true;
        }

        // ---------- Modal "Editar monto" (solo fondos recurrentes activos) ----------
        const mostrarModalEditar = ref(false);
        const refModalEditar = ref(null);
        const fondoEditando = ref(null);
        const montoEditar = ref('');
        const errorEditar = ref(null);
        const guardandoEditar = ref(false);

        watch(mostrarModalEditar, (abierto) => { abierto ? refModalEditar.value?.showModal() : refModalEditar.value?.close(); });

        function abrirModalEditar(fondo) {
            fondoEditando.value = fondo;
            montoEditar.value = fondo.monto_asignado;
            errorEditar.value = null;
            mostrarModalEditar.value = true;
        }

        function cerrarModalEditar() {
            mostrarModalEditar.value = false;
            fondoEditando.value = null;
            montoEditar.value = '';
            errorEditar.value = null;
        }

        async function actualizarMonto() {
            if (!fondoEditando.value) return;

            errorEditar.value = null;
            guardandoEditar.value = true;
            const formData = new FormData();
            formData.append('fondo_id', fondoEditando.value.fondo_id);
            formData.append('monto_asignado', montoEditar.value);

            try {
                const r = await fetch(datos.baseUrl + '/actualizar-monto-fondo', { method: 'POST', body: formData });
                const resultado = await r.json();
                if (resultado.ok) {
                    window.location.reload();
                    return;
                }
                errorEditar.value = resultado.error;
            } catch (e) {
                errorEditar.value = 'No se pudo conectar con el servidor.';
            } finally {
                guardandoEditar.value = false;
            }
        }

        return {
            datos, hoy,
            mostrarFiltros, hayFiltrosActivos, limpiarFiltros,
            filtroTexto, filtroEmpresa, filtroTipo, filtroEstado, filtroDesde, filtroHasta, empresasEnLista, fondosFiltrados,
            mostrarModalAsignar, refModalAsignar, cerrarModalAsignar, form, error, guardando, fondoCreadoId, asignar,
            mostrarModalEditar, refModalEditar, fondoEditando, montoEditar, errorEditar, guardandoEditar,
            abrirModalEditar, cerrarModalEditar, actualizarMonto,
            busquedaTrabajador, mostrarSugerencias, sugerenciasTrabajador, trabajadorSeleccionado, elegirTrabajador, ocultarSugerenciasConRetraso,
            formatearFecha, formatearSoles, quitarTrabajadorSeleccionado
        };
    },
}).mount('#app');
