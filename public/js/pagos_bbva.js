import {
    formatearFecha,
    formatearSoles,
    numeroDocumento,
} from './utils/formatos.js';
import { descargarTxtDesdeRespuesta } from './utils/descargar_txt.js';

const {
    createApp,
    ref,
    computed,
    watch,
} = Vue;

createApp({
    setup() {
        const datos = window.DATOS_INICIALES;

        const empresasDisponibles = new Set(datos.empresas.map(empresa => String(empresa.id)));
        const parametrosIniciales = new URLSearchParams(window.location.search);
        const empresaDesdeUrl = parametrosIniciales.get('empresa_id') || '';
        const empresaGuardada = sessionStorage.getItem('pagos_bbva_empresa_id') || '';
        const empresaInicial = empresasDisponibles.has(empresaDesdeUrl)
            ? empresaDesdeUrl
            : (empresasDisponibles.has(empresaGuardada) ? empresaGuardada : '');

        const empresaId = ref(empresaInicial);
        const empresa = ref(null);
        const pagos = ref([]);
        const fondos = ref([]);
        const vista = ref(datos.vistaInicial);

        const seleccionadas = ref([]);
        const fondosSeleccionados = ref([]);
        const seleccionadasReactivar = ref([]);
        const fondosSeleccionadosReactivar = ref([]);

        const cargando = ref(false);
        const generando = ref(false);
        const generandoFondos = ref(false);
        const reactivando = ref(false);
        const reactivandoFondos = ref(false);
        const guardandoCuenta = ref(false);

        const cuentaEmpresa = ref('');
        const confirmacionCuentaEmpresa = ref('');
        const error = ref(null);
        const errorCuenta = ref(null);
        const mensaje = ref(null);

        const dialogoDescarga = ref(null);
        const dialogoDesembolsoFondo = ref(null);
        const dialogoReactivar = ref(null);
        const dialogoReactivarFondos = ref(null);
        const dialogoCuenta = ref(null);

        const cajasHabilitadas = computed(() => pagos.value.filter(caja => caja.habilitada));
        const descargadas = computed(() => pagos.value.filter(caja => caja.descargada));
        const fondosPendientes = computed(() =>
            fondos.value.filter(fondo => fondo.estado === 'PENDIENTE_DESEMBOLSO')
        );
        const fondosHabilitados = computed(() =>
            fondosPendientes.value.filter(fondo => fondo.habilitada)
        );
        const fondosDesembolsados = computed(() =>
            fondos.value.filter(fondo =>
                fondo.estado === 'ACTIVO'
                && fondo.fecha_desembolso
                && Number(fondo.tiene_cajas) === 0
            )
        );

        // control de checks si todos o ninguno
        const todosSeleccionados = computed({
            get() {
                return cajasHabilitadas.value.length > 0 && cajasHabilitadas.value.every(caja => seleccionadas.value.includes(Number(caja.id)));
            },
            set(seleccionar) {
                seleccionadas.value = seleccionar ? cajasHabilitadas.value.map(caja => Number(caja.id)) : [];
            }
        });

        const todosFondosSeleccionados = computed({
            get() {
                return fondosHabilitados.value.length > 0
                    && fondosHabilitados.value.every(fondo =>
                        fondosSeleccionados.value.includes(Number(fondo.id))
                    );
            },
            set(seleccionar) {
                fondosSeleccionados.value = seleccionar
                    ? fondosHabilitados.value.map(fondo => Number(fondo.id))
                    : [];
            },
        });

        const todosFondosReactivarSeleccionados = computed({
            get() {
                return fondosDesembolsados.value.length > 0
                    && fondosDesembolsados.value.every(fondo =>
                        fondosSeleccionadosReactivar.value.includes(Number(fondo.id))
                    );
            },
            set(seleccionar) {
                fondosSeleccionadosReactivar.value = seleccionar
                    ? fondosDesembolsados.value.map(fondo => Number(fondo.id))
                    : [];
            },
        });

        const descargadasSeleccionadas = computed({
            get() {
                return descargadas.value.length > 0 && descargadas.value.every(caja => seleccionadasReactivar.value.includes(Number(caja.id)));
            },
            set(seleccionar) {
                seleccionadasReactivar.value = seleccionar ? descargadas.value.map(caja => Number(caja.id)) : [];
            },
        })

        const totalSeleccionado = computed(() => 
            pagos.value
                .filter(caja => seleccionadas.value.includes(Number(caja.id)))
                .reduce((total, caja) => total + Number(caja.importe || 0), 0
            )
        );

        const totalFondosSeleccionados = computed(() =>
            fondos.value
                .filter(fondo => fondosSeleccionados.value.includes(Number(fondo.id)))
                .reduce((total, fondo) => total + Number(fondo.importe || 0), 0)
        );

        watch(empresaId, async () => {
            empresa.value = null;
            pagos.value = [];
            fondos.value = [];
            seleccionadas.value = [];
            fondosSeleccionados.value = [];
            seleccionadasReactivar.value = [];
            fondosSeleccionadosReactivar.value = [];
            error.value = null;
            mensaje.value = null;

            guardarNavegacion();

            if (empresaId.value) await cargarEmpresa();
        }, { immediate: true });

        function guardarNavegacion() {
            const parametros = new URLSearchParams(window.location.search);

            if (empresaId.value) {
                sessionStorage.setItem('pagos_bbva_empresa_id', empresaId.value);
                parametros.set('empresa_id', empresaId.value);
            } else {
                sessionStorage.removeItem('pagos_bbva_empresa_id');
                parametros.delete('empresa_id');
            }

            if (vista.value === 'descargadas') parametros.set('modo', 'reactivacion');
            else parametros.delete('modo');

            const consulta = parametros.toString();
            window.history.replaceState(null, '', `${window.location.pathname}${consulta ? `?${consulta}` : ''}`);
        }

        function cambiarVista(nuevaVista) {
            vista.value = nuevaVista;
            error.value = null;
            mensaje.value = null;
            guardarNavegacion();
        }
        
        async function cargarEmpresa() {
            cargando.value = true;
            error.value = null;

            try {
                const respuesta = await fetch(
                    `${datos.baseUrl}/listar-pagos-bbva` + `?empresa_id=${encodeURIComponent(empresaId.value)}`
                );

                const resultado = await respuesta.json();

                if (!resultado.ok) {
                    error.value = resultado.error;
                    return;
                }

                empresa.value = resultado.empresa;
                pagos.value = resultado.pagos;
                fondos.value = resultado.fondos || [];
            } catch (e) {
                error.value = 'No se pudo conectar con el servidor.';
            } finally {
                cargando.value = false;
            }
        }

        function limpiarCuentaEmpresa() {
            cuentaEmpresa.value = cuentaEmpresa.value.replace(/\D/g, '');
            confirmacionCuentaEmpresa.value = confirmacionCuentaEmpresa.value.replace(/\D/g, '');
        }

        function abrirConfiguracionCuenta() {
            errorCuenta.value = null;
            cuentaEmpresa.value = '';
            confirmacionCuentaEmpresa.value = '';
            dialogoCuenta.value?.showModal();
        }

        function cerrarConfiguracionCuenta() {
            dialogoCuenta.value?.close();
            errorCuenta.value = null;
            cuentaEmpresa.value = '';
            confirmacionCuentaEmpresa.value = '';
        }

        async function guardarCuentaEmpresa() {
            errorCuenta.value = null;

            if (!/^\d{20}$/.test(cuentaEmpresa.value)) {
                errorCuenta.value = 'La cuenta debe tener exactamente 20 dígitos.';
                return;
            }

            if (cuentaEmpresa.value !== confirmacionCuentaEmpresa.value) {
                errorCuenta.value = 'Las cuentas ingresadas no coinciden.';
                return;
            }

            guardandoCuenta.value = true;

            const formData = new FormData();
            formData.append('empresa_id', empresaId.value);
            formData.append('cuenta', cuentaEmpresa.value);
            formData.append('confirmacion_cuenta', confirmacionCuentaEmpresa.value);

            try {
                const respuesta = await fetch(`${datos.baseUrl}/guardar-cuenta-empresa-bbva`, { method: 'POST', body: formData});
                const resultado = await respuesta.json();

                if (!resultado.ok) {
                    errorCuenta.value = resultado.error;
                    return;
                }

                cerrarConfiguracionCuenta();
                mensaje.value = 'Cuenta guardada correctamente.';
                await cargarEmpresa();
            } catch (e) {
                errorCuenta.value = 'No se pudo conectar con el servidor.';
            } finally {
                guardandoCuenta.value = false;
            }
        }

        function abrirConfirmacionDescarga() {
            error.value = null;
            
            if (seleccionadas.value.length === 0) {
                error.value = 'Selecciona al menos una caja.';
                return;
            }

            dialogoDescarga.value?.showModal();
        }

        function cerrarConfirmacionDescarga() {
            dialogoDescarga.value?.close();
        }

        function abrirConfirmacionDesembolso() {
            error.value = null;
            if (fondosSeleccionados.value.length === 0) {
                error.value = 'Selecciona al menos un fondo.';
                return;
            }
            dialogoDesembolsoFondo.value?.showModal();
        }

        function cerrarConfirmacionDesembolso() {
            dialogoDesembolsoFondo.value?.close();
        }

        async function descargarTxtFondos() {
            generandoFondos.value = true;
            error.value = null;
            mensaje.value = null;

            const formData = new FormData();
            formData.append('empresa_id', empresaId.value);
            fondosSeleccionados.value.forEach(id => formData.append('fondos[]', id));

            try {
                const respuesta = await fetch(
                    `${datos.baseUrl}/generar-txt-fondos`,
                    { method: 'POST', body: formData }
                );

                if (!respuesta.ok) {
                    const resultado = await respuesta.json();
                    error.value = resultado.error;
                    cerrarConfirmacionDesembolso();
                    await cargarEmpresa();
                    return;
                }

                await descargarTxtDesdeRespuesta(respuesta, 'DESEMBOLSO_FONDOS_BBVA.txt');

                cerrarConfirmacionDesembolso();
                fondosSeleccionados.value = [];
                mensaje.value = 'La descarga del desembolso fue registrada.';
                await cargarEmpresa();
            } catch (e) {
                error.value = 'La descarga se interrumpió. Un administrador puede reactivar el fondo si todavía no tiene resúmenes.';
                cerrarConfirmacionDesembolso();
                await cargarEmpresa();
            } finally {
                generandoFondos.value = false;
            }
        }

        async function descargarTxt() {
            generando.value = true;
            error.value = null;
            mensaje.value = null;

            const formData = new FormData();

            formData.append('empresa_id', empresaId.value);
            seleccionadas.value.forEach(id => {
                formData.append('cajas[]', id);
            });

            try {
                const respuesta = await fetch(`${datos.baseUrl}/generar-txt-bbva`, { method: 'POST', body: formData});

                if (!respuesta.ok) {
                    const resultado = await respuesta.json();
                    error.value = resultado.error;
                    cerrarConfirmacionDescarga();
                    await cargarEmpresa();
                    return;
                }

                await descargarTxtDesdeRespuesta(respuesta, 'PAGO_CAJA_CHICA_BBVA.txt');

                cerrarConfirmacionDescarga();
                seleccionadas.value = [];
                mensaje.value = 'La descarga fue registrada';
                await cargarEmpresa();
            } catch (e) {
                error.value = 'La descarga se interrumpió. Revisa la pestaña ' + '"Descargas registradas" antes de volver a intentarlo.';

                cerrarConfirmacionDescarga();
                await cargarEmpresa();
            } finally {
                generando.value = false;
            }
        }

        function abrirReactivacion() {
            error.value = null;
            dialogoReactivar.value?.showModal();
        }

        function cerrarReactivacion() {
            dialogoReactivar.value?.close();
        }

        function abrirReactivacionFondos() {
            error.value = null;
            if (fondosSeleccionadosReactivar.value.length === 0) {
                error.value = 'Selecciona al menos un fondo.';
                return;
            }
            dialogoReactivarFondos.value?.showModal();
        }

        function cerrarReactivacionFondos() {
            dialogoReactivarFondos.value?.close();
        }

        async function reactivarFondos() {
            reactivandoFondos.value = true;
            error.value = null;
            mensaje.value = null;

            const formData = new FormData();
            formData.append('empresa_id', empresaId.value);
            fondosSeleccionadosReactivar.value.forEach(id => formData.append('fondos[]', id));

            try {
                const respuesta = await fetch(
                    `${datos.baseUrl}/reactivar-txt-fondos`,
                    { method: 'POST', body: formData }
                );
                const resultado = await respuesta.json();

                if (!resultado.ok) {
                    error.value = resultado.error;
                    return;
                }

                cerrarReactivacionFondos();
                fondosSeleccionadosReactivar.value = [];
                mensaje.value = 'Los fondos volvieron a estar pendientes de desembolso.';
                await cargarEmpresa();
            } catch (e) {
                error.value = 'No se pudo conectar con el servidor.';
            } finally {
                reactivandoFondos.value = false;
            }
        }

        async function reactivar() {
            error.value = null;
            mensaje.value = null;

            reactivando.value = true;

            const formData = new FormData();
            formData.append('empresa_id', empresaId.value);

            seleccionadasReactivar.value.forEach(id => {
                formData.append('cajas[]', id);
            });
            try {
                const respuesta = await fetch(
                    `${datos.baseUrl}/reactivar-txt-bbva`, {method: 'POST', body: formData}
                );

                const resultado = await respuesta.json();

                if (!resultado.ok) {
                    error.value = resultado.error;
                    return;
                }

                cerrarReactivacion();
                seleccionadasReactivar.value = [];
                mensaje.value = 'Las cajas volvieron a estar habilitadas para descargar su TXT.';
                await cargarEmpresa();
            } catch (e) {
                error.value = 'No se pudo conectar con el servidor.';
            } finally {
                reactivando.value = false;
            }
        }

        
        return {
            datos,
            empresaId,
            empresa,
            pagos,
            fondos,
            fondosPendientes,
            fondosHabilitados,
            fondosDesembolsados,
            descargadas,
            vista,
            cambiarVista,
            seleccionadas,
            fondosSeleccionados,
            fondosSeleccionadosReactivar,
            seleccionadasReactivar,
            cajasHabilitadas,
            todosSeleccionados,
            todosFondosSeleccionados,
            todosFondosReactivarSeleccionados,
            descargadasSeleccionadas,
            totalSeleccionado,
            totalFondosSeleccionados,
            cargando,
            generando,
            generandoFondos,
            reactivando,
            reactivandoFondos,
            guardandoCuenta,
            cuentaEmpresa,
            confirmacionCuentaEmpresa,
            error,
            errorCuenta,
            mensaje,
            dialogoDescarga,
            dialogoDesembolsoFondo,
            dialogoReactivar,
            dialogoReactivarFondos,
            dialogoCuenta,
            cargarEmpresa,
            limpiarCuentaEmpresa,
            abrirConfiguracionCuenta,
            cerrarConfiguracionCuenta,
            guardarCuentaEmpresa,
            abrirConfirmacionDescarga,
            cerrarConfirmacionDescarga,
            descargarTxt,
            abrirConfirmacionDesembolso,
            cerrarConfirmacionDesembolso,
            descargarTxtFondos,
            abrirReactivacion,
            cerrarReactivacion,
            reactivar,
            abrirReactivacionFondos,
            cerrarReactivacionFondos,
            reactivarFondos,
            numeroDocumento,
            formatearFecha,
            formatearSoles
        };
    },
}).mount('#app');
