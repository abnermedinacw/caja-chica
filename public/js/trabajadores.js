import { coincideEnAlguno, coincideExacto } from './utils/filtros.js';
import { enviarFormulario, obtenerJson } from './utils/api.js';

const { createApp, ref, computed, watch } = Vue;

createApp({
    setup() {
        const datos = window.DATOS_INICIALES || { baseUrl: '', trabajadores: [], empresas: [] };

        // Filtros
        const mostrarFiltros = ref(false);
        const filtroTexto = ref('');
        const filtroEmpresa = ref('');

        const hayFiltrosActivos = computed(() => !!(filtroTexto.value || filtroEmpresa.value));
        function limpiarFiltros() {
            filtroTexto.value = '';
            filtroEmpresa.value = '';
        }

        //Empresas en lista
        const empresasEnLista = computed(() => [...new Set(datos.trabajadores.map(t => t.empresa))].sort());

        const trabajadoresFiltrados = computed(() =>
            datos.trabajadores.filter(t =>
                coincideEnAlguno(t, ['dni', 'nombres'], filtroTexto.value) &&
                coincideExacto(t.empresa, filtroEmpresa.value)
            )
        );

        const mostrarModalSincronizar = ref(false);
        const refModalSincronizar = ref(null);
        watch(mostrarModalSincronizar, (abierto) => {
            abierto ? refModalSincronizar.value?.showModal() : refModalSincronizar.value?.close();
        });

        const dniBusqueda = ref('');
        const buscando = ref(false);
        const errorBusqueda = ref(null);
        const trabajadorEncontrado = ref(false);

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
                username: '',
                correo: '',
                password: '',
                rol: 'SOLICITANTE',
                cuenta_bbva: '',
                advertencia_cuenta: '',
                es_admin: false,
                es_jefe: false,
            };
        }

        const form = ref(crearFormularioInicial());

        const areasFiltradas = computed(() => {
            const areas = datos.trabajadores
                .map(t => t.area)
                .filter(Boolean);

            if (form.value.area) areas.push(form.value.area);
            return [...new Set(areas)].sort();
        })

        async function buscarPorDni() {
            errorBusqueda.value = null;
            if (!dniBusqueda.value.trim()) return;
            buscando.value = true;

            // Llama al endpoint de búsqueda externa
            const r = await obtenerJson(`${datos.baseUrl}/buscar-trabajador-ts?dni=${dniBusqueda.value.trim()}`);
            buscando.value = false;
            if (!r.ok) {
                errorBusqueda.value = r.error;
                return;
            }

            form.value = { ...crearFormularioInicial(), ...r.datos };
            form.value.area = r.datos.area || '';
            trabajadorEncontrado.value = true;
        }

        const guardando = ref(false);
        const errorGuardado = ref(null);

        async function guardar() {
            if (!form.value.area) {
                errorGuardado.value = 'Debes seleccionar un área.';
                return;
            }
            if (!form.value.correo) {
                errorGuardado.value = 'Ingresa correo del usuario.';
                return;
            }
            errorGuardado.value = null;
            guardando.value = true;

            // Llama al endpoint de sincronización
            const r = await enviarFormulario(`${datos.baseUrl}/sincronizar-trabajador`, form.value);
            guardando.value = false;
            if (!r.ok) {
                errorGuardado.value = r.error;
                return;
            }
            // Recarga para que trabajador aparezca en pantalla
            window.location.reload();
        }

        function volverABuscar() {
            trabajadorEncontrado.value = false;
            dniBusqueda.value = '';
            errorBusqueda.value = null;
            errorGuardado.value = null;
            form.value = crearFormularioInicial();
        }

        function cerrarModal() {
            mostrarModalSincronizar.value = false;
            volverABuscar();
        }

        return {
            datos,
            mostrarFiltros, hayFiltrosActivos, limpiarFiltros,
            filtroTexto, filtroEmpresa, empresasEnLista, trabajadoresFiltrados,
            mostrarModalSincronizar, refModalSincronizar,
            dniBusqueda, buscando, errorBusqueda, trabajadorEncontrado,
            form, areasFiltradas, buscarPorDni,
            guardando, errorGuardado, guardar,
            volverABuscar, cerrarModal,
        };
    },
}).mount('#app');
