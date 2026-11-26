import {
    formatearFecha,
    formatearSoles,
    numeroDocumento,
} from './utils/formatos.js';
import { descargarTxtReporte } from './utils/descargar_txt.js';

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

        const bancoDesdeUrl = (parametrosIniciales.get('banco') || '').toUpperCase();
        const bancoGuardado = (sessionStorage.getItem('pagos_banco_activo') || '').toUpperCase();
        const bancoInicial = ['BBVA', 'BCP'].includes(bancoDesdeUrl)
            ? bancoDesdeUrl
            : (['BBVA', 'BCP'].includes(bancoGuardado) ? bancoGuardado : 'BBVA');

        const empresaId = ref(empresaInicial);
        const banco = ref(bancoInicial);
        const empresa = ref(null);
        const pagos = ref([]);
        const fondos = ref([]);
        const cuentasPendientes = ref(datos.cuentasPendientes || []);
        const fechaHoyLima = ref(datos.fechaHoyLima || '');
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
        const validandoCuenta = ref(false);

        const cuentaEmpresa = ref('');
        const confirmacionCuentaEmpresa = ref('');
        const tipoCuentaEmpresaBcp = ref('C');
        const cuentaPorValidar = ref(null);

        const error = ref(null);
        const errorCuenta = ref(null);
        const errorValidacionCuenta = ref(null);
        const mensaje = ref(null);

        const dialogoDescarga = ref(null);
        const dialogoDesembolsoFondo = ref(null);
        const dialogoReactivar = ref(null);
        const dialogoReactivarFondos = ref(null);
        const dialogoCuenta = ref(null);
        const dialogoValidarCuenta = ref(null);

        // Listados base
        const cajasPendientesTodas = computed(() => pagos.value.filter(caja => !caja.descargada));
        const descargadas = computed(() => pagos.value.filter(caja => caja.descargada));
        const fondosPendientesTodos = computed(() =>
            fondos.value.filter(fondo => fondo.estado === 'PENDIENTE_DESEMBOLSO')
        );
        const fondosDesembolsados = computed(() =>
            fondos.value.filter(fondo =>
                fondo.estado === 'ACTIVO'
                && fondo.fecha_desembolso
                && Number(fondo.tiene_cajas) === 0
            )
        );

        // Filtrado estricto por el banco predeterminado de cada trabajador
        const cajasPendientesBanco = computed(() =>
            cajasPendientesTodas.value.filter(caja => caja.banco_destino === banco.value)
        );
        const cajasHabilitadasBanco = computed(() =>
            cajasPendientesBanco.value.filter(caja => caja.habilitada)
        );
        const fondosPendientesBanco = computed(() =>
            fondosPendientesTodos.value.filter(fondo => fondo.banco_destino === banco.value)
        );
        const fondosHabilitadosBanco = computed(() =>
            fondosPendientesBanco.value.filter(fondo => fondo.habilitada)
        );

        const registrosSinBanco = computed(() => [
            ...cajasPendientesTodas.value.filter(c => !c.banco_destino),
            ...fondosPendientesTodos.value.filter(f => !f.banco_destino),
        ]);

        const conteoPendientesPorBanco = computed(() => ({
            BBVA: cajasPendientesTodas.value.filter(c => c.banco_destino === 'BBVA').length
                + fondosPendientesTodos.value.filter(f => f.banco_destino === 'BBVA').length,
            BCP: cajasPendientesTodas.value.filter(c => c.banco_destino === 'BCP').length
                + fondosPendientesTodos.value.filter(f => f.banco_destino === 'BCP').length,
        }));

        const tieneCuentaCargoActiva = computed(() => {
            if (!empresa.value) return false;
            return banco.value === 'BCP'
                ? Boolean(empresa.value.cuenta_cargo_bcp)
                : Boolean(empresa.value.cuenta_cargo_bbva);
        });

        const cuentaCargoActivaEnmascarada = computed(() => {
            if (!empresa.value) return '-';
            return banco.value === 'BCP'
                ? (empresa.value.cuenta_enmascarada_bcp || '-')
                : (empresa.value.cuenta_enmascarada_bbva || empresa.value.cuenta_enmascarada || '-');
        });

        const fechaActualizacionCargoActiva = computed(() => {
            if (!empresa.value) return null;
            return banco.value === 'BCP'
                ? empresa.value.cuenta_cargo_bcp_fecha_actualizacion
                : empresa.value.cuenta_cargo_bbva_fecha_actualizacion;
        });

        // Controles de checks (acotados siempre al banco activo)
        const todosSeleccionados = computed({
            get() {
                return cajasHabilitadasBanco.value.length > 0
                    && cajasHabilitadasBanco.value.every(caja => seleccionadas.value.includes(Number(caja.id)));
            },
            set(seleccionar) {
                seleccionadas.value = seleccionar ? cajasHabilitadasBanco.value.map(caja => Number(caja.id)) : [];
            },
        });

        const todosFondosSeleccionados = computed({
            get() {
                return fondosHabilitadosBanco.value.length > 0
                    && fondosHabilitadosBanco.value.every(fondo =>
                        fondosSeleccionados.value.includes(Number(fondo.id))
                    );
            },
            set(seleccionar) {
                fondosSeleccionados.value = seleccionar
                    ? fondosHabilitadosBanco.value.map(fondo => Number(fondo.id))
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
                return descargadas.value.length > 0
                    && descargadas.value.every(caja => seleccionadasReactivar.value.includes(Number(caja.id)));
            },
            set(seleccionar) {
                seleccionadasReactivar.value = seleccionar ? descargadas.value.map(caja => Number(caja.id)) : [];
            },
        });

        const totalSeleccionado = computed(() =>
            cajasPendientesBanco.value
                .filter(caja => seleccionadas.value.includes(Number(caja.id)))
                .reduce((total, caja) => total + Number(caja.importe || 0), 0)
        );

        const totalFondosSeleccionados = computed(() =>
            fondosPendientesBanco.value
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

            sessionStorage.setItem('pagos_banco_activo', banco.value);
            parametros.set('banco', banco.value);

            if (vista.value === 'descargadas') parametros.set('modo', 'reactivacion');
            else parametros.delete('modo');

            const consulta = parametros.toString();
            window.history.replaceState(null, '', `${window.location.pathname}${consulta ? `?${consulta}` : ''}`);
        }

        function cambiarBanco(nuevoBanco) {
            if (banco.value === nuevoBanco) return;
            banco.value = nuevoBanco;
            // Limpiar selecciones al cambiar de banco para evitar cruces.
            seleccionadas.value = [];
            fondosSeleccionados.value = [];
            error.value = null;
            mensaje.value = null;
            guardarNavegacion();
        }

        function cambiarVista(nuevaVista) {
            vista.value = nuevaVista;
            error.value = null;
            mensaje.value = null;
            guardarNavegacion();
        }

        function actualizarBadgeSidebar(cantidad) {
            const badge = document.getElementById('badgeCuentasPendientes');
            if (!badge) return;
            if (cantidad > 0) {
                badge.textContent = String(cantidad);
                badge.hidden = false;
            } else {
                badge.remove();
            }
        }

        async function cargarEmpresa() {
            cargando.value = true;
            error.value = null;

            try {
                const respuesta = await fetch(
                    `${datos.baseUrl}/listar-pagos-bbva?empresa_id=${encodeURIComponent(empresaId.value)}`
                );
                const resultado = await respuesta.json();

                if (!resultado.ok) {
                    error.value = resultado.error;
                    return;
                }

                empresa.value = resultado.empresa;
                pagos.value = resultado.pagos;
                fondos.value = resultado.fondos || [];
                if (Array.isArray(resultado.cuentas_pendientes_validacion)) {
                    cuentasPendientes.value = resultado.cuentas_pendientes_validacion;
                    actualizarBadgeSidebar(cuentasPendientes.value.length);
                }
                if (resultado.fecha_hoy_lima) {
                    fechaHoyLima.value = resultado.fecha_hoy_lima;
                }
            } catch (e) {
                error.value = 'No se pudo conectar con el servidor.';
            } finally {
                cargando.value = false;
            }
        }

        function limpiarCuentaEmpresa() {
            const max = banco.value === 'BCP' ? 13 : 20;
            cuentaEmpresa.value = cuentaEmpresa.value.replace(/\D/g, '').slice(0, max);
            confirmacionCuentaEmpresa.value = confirmacionCuentaEmpresa.value.replace(/\D/g, '').slice(0, max);
        }

        function abrirConfiguracionCuenta() {
            errorCuenta.value = null;
            cuentaEmpresa.value = '';
            confirmacionCuentaEmpresa.value = '';
            tipoCuentaEmpresaBcp.value = empresa.value?.tipo_cuenta_cargo_bcp || 'C';
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
            const longitud = banco.value === 'BCP' ? 13 : 20;
            const regex = new RegExp(`^\\d{${longitud}}$`);

            if (!regex.test(cuentaEmpresa.value)) {
                errorCuenta.value = `La cuenta ${banco.value} debe tener exactamente ${longitud} dígitos.`;
                return;
            }
            if (cuentaEmpresa.value !== confirmacionCuentaEmpresa.value) {
                errorCuenta.value = 'Las cuentas ingresadas no coinciden.';
                return;
            }

            guardandoCuenta.value = true;

            const formData = new FormData();
            formData.append('empresa_id', empresaId.value);
            formData.append('banco', banco.value);
            formData.append('cuenta', cuentaEmpresa.value);
            formData.append('confirmacion_cuenta', confirmacionCuentaEmpresa.value);
            if (banco.value === 'BCP') {
                formData.append('tipo_cuenta', tipoCuentaEmpresaBcp.value);
            }

            try {
                const respuesta = await fetch(`${datos.baseUrl}/guardar-cuenta-empresa-bbva`, { method: 'POST', body: formData });
                const resultado = await respuesta.json();

                if (!resultado.ok) {
                    errorCuenta.value = resultado.error;
                    return;
                }

                cerrarConfiguracionCuenta();
                mensaje.value = `Cuenta de cargo ${banco.value} guardada correctamente.`;
                await cargarEmpresa();
            } catch (e) {
                errorCuenta.value = 'No se pudo conectar con el servidor.';
            } finally {
                guardandoCuenta.value = false;
            }
        }

        function abrirValidacionCuenta(cuenta) {
            errorValidacionCuenta.value = null;
            cuentaPorValidar.value = cuenta;
            dialogoValidarCuenta.value?.showModal();
        }

        function cerrarValidacionCuenta() {
            dialogoValidarCuenta.value?.close();
            cuentaPorValidar.value = null;
            errorValidacionCuenta.value = null;
        }

        async function confirmarValidacionCuenta() {
            if (!cuentaPorValidar.value || validandoCuenta.value) return;
            validandoCuenta.value = true;
            errorValidacionCuenta.value = null;

            const formData = new FormData();
            formData.append('trabajador_id', String(cuentaPorValidar.value.trabajador_id));
            formData.append('banco', cuentaPorValidar.value.banco);

            try {
                const respuesta = await fetch(`${datos.baseUrl}/validar-cuenta-trabajador`, { method: 'POST', body: formData });
                const resultado = await respuesta.json();
                if (!resultado.ok) {
                    errorValidacionCuenta.value = resultado.error || 'No se pudo validar la cuenta.';
                    return;
                }

                const { trabajador_id, banco: bancoValidado } = cuentaPorValidar.value;
                cuentasPendientes.value = cuentasPendientes.value.filter(
                    c => !(Number(c.trabajador_id) === Number(trabajador_id) && c.banco === bancoValidado)
                );
                actualizarBadgeSidebar(cuentasPendientes.value.length);
                mensaje.value = resultado.mensaje;
                cerrarValidacionCuenta();
                if (empresaId.value) await cargarEmpresa();
            } catch (e) {
                errorValidacionCuenta.value = 'No se pudo conectar con el servidor.';
            } finally {
                validandoCuenta.value = false;
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
            formData.append('banco', banco.value);
            fondosSeleccionados.value.forEach(id => formData.append('fondos[]', id));

            try {
                const respuesta = await fetch(`${datos.baseUrl}/generar-txt-fondos`, { method: 'POST', body: formData });

                if (!respuesta.ok) {
                    const resultado = await respuesta.json();
                    error.value = resultado.error;
                    cerrarConfirmacionDesembolso();
                    await cargarEmpresa();
                    return;
                }

                await descargarTxtReporte(
                    respuesta,
                    `DESEMBOLSO_FONDOS_${banco.value}.txt`,
                    `REPORTE_DESEMBOLSO_FONDOS_${banco.value}.pdf`
                );

                cerrarConfirmacionDesembolso();
                fondosSeleccionados.value = [];
                mensaje.value = `La descarga del desembolso ${banco.value} fue registrada.`;
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
            formData.append('banco', banco.value);
            seleccionadas.value.forEach(id => formData.append('cajas[]', id));

            try {
                const respuesta = await fetch(`${datos.baseUrl}/generar-txt-bbva`, { method: 'POST', body: formData });

                if (!respuesta.ok) {
                    const resultado = await respuesta.json();
                    error.value = resultado.error;
                    cerrarConfirmacionDescarga();
                    await cargarEmpresa();
                    return;
                }

                await descargarTxtReporte(
                    respuesta,
                    `PAGO_CAJA_CHICA_${banco.value}.txt`,
                    `REPORTE_PAGO_CAJA_CHICA_${banco.value}.pdf`
                );

                cerrarConfirmacionDescarga();
                seleccionadas.value = [];
                mensaje.value = `La descarga ${banco.value} fue registrada.`;
                await cargarEmpresa();
            } catch (e) {
                error.value = 'La descarga se interrumpió. Revisa la pestaña "Descargas registradas" antes de volver a intentarlo.';
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
        function cerrarReactivacion() { dialogoReactivar.value?.close(); }

        function abrirReactivacionFondos() {
            error.value = null;
            if (fondosSeleccionadosReactivar.value.length === 0) {
                error.value = 'Selecciona al menos un fondo.';
                return;
            }
            dialogoReactivarFondos.value?.showModal();
        }
        function cerrarReactivacionFondos() { dialogoReactivarFondos.value?.close(); }

        async function reactivarFondos() {
            reactivandoFondos.value = true;
            error.value = null;
            mensaje.value = null;

            const formData = new FormData();
            formData.append('empresa_id', empresaId.value);
            fondosSeleccionadosReactivar.value.forEach(id => formData.append('fondos[]', id));

            try {
                const respuesta = await fetch(`${datos.baseUrl}/reactivar-txt-fondos`, { method: 'POST', body: formData });
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
            seleccionadasReactivar.value.forEach(id => formData.append('cajas[]', id));

            try {
                const respuesta = await fetch(`${datos.baseUrl}/reactivar-txt-bbva`, { method: 'POST', body: formData });
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
            banco,
            empresa,
            pagos,
            fondos,
            cuentasPendientes,
            fechaHoyLima,
            vista,
            cambiarBanco,
            cambiarVista,
            cajasPendientesBanco,
            cajasHabilitadasBanco,
            fondosPendientesBanco,
            fondosHabilitadosBanco,
            registrosSinBanco,
            conteoPendientesPorBanco,
            tieneCuentaCargoActiva,
            cuentaCargoActivaEnmascarada,
            fechaActualizacionCargoActiva,
            fondosDesembolsados,
            descargadas,
            seleccionadas,
            fondosSeleccionados,
            fondosSeleccionadosReactivar,
            seleccionadasReactivar,
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
            validandoCuenta,
            cuentaEmpresa,
            confirmacionCuentaEmpresa,
            tipoCuentaEmpresaBcp,
            cuentaPorValidar,
            error,
            errorCuenta,
            errorValidacionCuenta,
            mensaje,
            dialogoDescarga,
            dialogoDesembolsoFondo,
            dialogoReactivar,
            dialogoReactivarFondos,
            dialogoCuenta,
            dialogoValidarCuenta,
            limpiarCuentaEmpresa,
            abrirConfiguracionCuenta,
            cerrarConfiguracionCuenta,
            guardarCuentaEmpresa,
            abrirValidacionCuenta,
            cerrarValidacionCuenta,
            confirmarValidacionCuenta,
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
            formatearSoles,
        };
    },
}).mount('#app');
