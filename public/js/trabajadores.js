import { enviarFormulario, obtenerJson } from './utils/api.js';

const { createApp, ref, computed, watch } = Vue;

createApp({
    setup() {
        const datos = { baseUrl: '', trabajadores: [], empresas: [], areas: [], paginacion: {}, ...(window.DATOS_INICIALES || {})
        };

        // Filtros
        const filtroTexto = ref(datos.paginacion.buscar || '');
        const filtroEmpresa = ref(String(datos.paginacion.empresa_id || ''));
        const porPagina = ref(datos.paginacion.por_pagina || 25);
        const hayFiltrosActivos = computed(() => !!(filtroTexto.value || filtroEmpresa.value));
        const mostrarFiltros = ref(hayFiltrosActivos.value || porPagina.value !== 25);

        const mostrarModalSincronizar = ref(false);
        const refModalSincronizar = ref(null);
        watch(mostrarModalSincronizar, (abierto) => {
            abierto ? refModalSincronizar.value?.showModal() : refModalSincronizar.value?.close();
        });

        const dniBusqueda = ref('');
        const buscando = ref(false);
        const guardando = ref(false);
        const errorBusqueda = ref(null);
        const errorGuardado = ref(null);
        const trabajadorEncontrado = ref(false);

        // Autocompletar del jefe manual: busca en servidor, sin precargar
        // todos los responsables de la empresa.
        const busquedaJefe = ref('');
        const mostrarSugerenciasJefe = ref(false);
        const sugerenciasJefe = ref([]);
        const buscandoJefe = ref(false);
        const jefeSeleccionado = ref(null);
        let temporizadorJefe = null;
        let peticionJefe = 0;

        //datos precargados de la bbdd externa
        function crearFormularioInicial() {
            return {
                dni: '',
                nombres: '',
                matriz_id: '',
                matriz_razon_social: '',
                esCliente: false,
                cliente_ruc: '',
                cliente_razon_social: '',
                cargo: '',
                area: '',
                area_ts: false,
                area_id: null,
                username: '',
                correo: '',
                password: '',
                rol: 'SOLICITANTE',
                cuenta_bbva: '',
                advertencia_cuenta: '',
                es_admin: false,
                jefe_ts: false,
                jefe_dni: '',
                jefe_nombre: '',
                jefe_usuario_id: null,
                error_jefe: '',
                grupos_a_cargo: [],
            };
        }

        const form = ref(crearFormularioInicial());

        const areasDeMatriz = computed(() => datos.areas.filter(
            a => Number(a.empresa_id) === Number(form.value.matriz_id)
        ));

        function reiniciarAutocompletarJefe() {
            if (temporizadorJefe) {
                clearTimeout(temporizadorJefe);
                temporizadorJefe = null;
            }
            busquedaJefe.value = '';
            sugerenciasJefe.value = [];
            mostrarSugerenciasJefe.value = false;
            buscandoJefe.value = false;
            jefeSeleccionado.value = null;
            peticionJefe++;
        }

        function programarBusquedaJefe() {
            mostrarSugerenciasJefe.value = true;
            if (temporizadorJefe) clearTimeout(temporizadorJefe);

            const termino = busquedaJefe.value.trim();
            if (termino.length < 2 || !form.value.matriz_id) {
                sugerenciasJefe.value = [];
                buscandoJefe.value = false;
                peticionJefe++;
                return;
            }

            temporizadorJefe = setTimeout(buscarJefes, 250);
        }

        async function buscarJefes() {
            const termino = busquedaJefe.value.trim();
            if (termino.length < 2 || !form.value.matriz_id) return;

            const peticionActual = ++peticionJefe;
            buscandoJefe.value = true;
            try {
                const params = new URLSearchParams({
                    q: termino,
                    empresa_id: String(form.value.matriz_id),
                    dni: String(form.value.dni || ''),
                });
                const r = await obtenerJson(`${datos.baseUrl}/buscar-responsable?${params.toString()}`);
                if (peticionActual !== peticionJefe) return;
                sugerenciasJefe.value = r.ok ? (r.responsables || []) : [];
            } finally {
                if (peticionActual === peticionJefe) buscandoJefe.value = false;
            }
        }

        function elegirJefe(j) {
            jefeSeleccionado.value = j;
            form.value.jefe_usuario_id = Number(j.usuario_id);
            busquedaJefe.value = '';
            sugerenciasJefe.value = [];
            mostrarSugerenciasJefe.value = false;
        }

        function limpiarJefe() {
            form.value.jefe_usuario_id = null;
            reiniciarAutocompletarJefe();
        }

        function ocultarSugerenciasJefeConRetraso() {
            setTimeout(() => { mostrarSugerenciasJefe.value = false; }, 150);
        }

        async function buscarPorDni() {
            if (buscando.value || guardando.value || !dniBusqueda.value.trim()) return;
            errorBusqueda.value = null;
            buscando.value = true;

            try {
                const dni = encodeURIComponent(dniBusqueda.value.trim());
                const r = await obtenerJson(`${datos.baseUrl}/buscar-trabajador-ts?dni=${dni}`);
                if (!r.ok) {
                    errorBusqueda.value = r.error;
                    return;
                }
                form.value = {...crearFormularioInicial(), ...r.datos};
                trabajadorEncontrado.value = true;
                reiniciarAutocompletarJefe();
            } finally {
                buscando.value = false;
            }
        }

        async function guardar() {
            if (guardando.value) return;
            errorGuardado.value = null;
            if (!form.value.area_ts && !form.value.area_id) {
                errorGuardado.value = 'Selecciona un área laboral.';
                return;
            }
            if (form.value.error_jefe) {
                errorGuardado.value = form.value.error_jefe;
                return;
            }
            if (!form.value.jefe_ts && !form.value.jefe_usuario_id) {
                errorGuardado.value = 'Selecciona el jefe responsable';
                return;
            }
            guardando.value = true;
            try {
                const { dni, area_id, jefe_usuario_id, username, correo, password, rol, es_admin } = form.value;
                const r = await enviarFormulario(`${datos.baseUrl}/sincronizar-trabajador`, {
                    dni, area_id, jefe_usuario_id, username, correo, password, rol, es_admin,
                });
                if (!r.ok) {
                    errorGuardado.value = r.error;
                    return;
                }
                window.location.assign(`${datos.baseUrl}/trabajadores`);
            } finally {
                guardando.value = false;
            }
        }

        function volverABuscar() {
            if (guardando.value || buscando.value) return;
            trabajadorEncontrado.value = false;
            dniBusqueda.value = '';
            errorBusqueda.value = null;
            errorGuardado.value = null;
            form.value = crearFormularioInicial();
            reiniciarAutocompletarJefe();
        }

        function cerrarModal() {
            if (guardando.value || buscando.value) return;
            mostrarModalSincronizar.value = false;
            volverABuscar();
        }

        return {
            datos, mostrarFiltros, hayFiltrosActivos,
            filtroTexto, filtroEmpresa, porPagina,
            mostrarModalSincronizar, refModalSincronizar,
            dniBusqueda, buscando, errorBusqueda, trabajadorEncontrado,
            form, areasDeMatriz, buscarPorDni,
            busquedaJefe, mostrarSugerenciasJefe, sugerenciasJefe, buscandoJefe, jefeSeleccionado,
            programarBusquedaJefe, elegirJefe, limpiarJefe, ocultarSugerenciasJefeConRetraso,
            guardando, errorGuardado, guardar, volverABuscar, cerrarModal
        };
    },
}).mount('#app');
