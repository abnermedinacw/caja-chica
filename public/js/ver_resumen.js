import { itemContableVacio, validarItemContableCliente } from './resumen/validacionComprobante.js';
import { formatearFecha, formatearSoles, numeroDocumento } from './utils/formatos.js';
import { enviarFormulario } from './utils/api.js';
import { usarModales } from './resumen/modales.js';
import { usarToast } from './resumen/toast.js';
import { usarCabecera } from './resumen/cabecera.js';
import { usarVerificacionRuc } from './resumen/verificacionRuc.js';
import { crearPanelDocumento } from './resumen/panelDocumento.js';

const { createApp, ref, computed, watch } = Vue;

function itemNoContableVacio() {
    return { tipo_documento_id: '', descripcion: '', fecha_documento: '', monto: '', numero_paginas: '', observaciones: '' };
}
function itemPlanillaVacio() {
    return { fecha: '', motivo: '', origen: '', destino: '', monto: '', tipo_evidencia: 'NINGUNO', observacion_evidencia: '' };
}

createApp({
    setup() {
        const datos = window.DATOS_INICIALES || {
            baseUrl: '', hoy: '', resumenId: 0, esEditable: false, resumen: { periodo_inicio: '', periodo_fin: '' },
            tiposComprobante: [], documentoContable: null, itemsDocumentoContable: [],
            tiposDocumentoNoContable: [], documentoNoContable: null, itemsDocumentoNoContable: [],
            planilla: null, itemsPlanilla: [], planillasDisponibles: [],
        };

        const resumen = datos.resumen;
        const esEditable = datos.esEditable;
        const resumenEstado = datos.resumenEstado;
        const hoy = datos.hoy;

        const mostrarSelectorTipo = ref(false);
        const modoPlanilla = ref(datos.planillasDisponibles.length > 0 ? null : 'nueva');

        const { dialogoConfirmar, refDialogoConfirmar, pedirConfirmacion, confirmarAccion, cancelarConfirmacion,
            dialogoAviso, refDialogoAviso, mostrarAviso, cerrarAviso } = usarModales();

        const { toast, guardarYRecargar, salioConGuardado } = usarToast();

        const { cabeceraEditando, guardandoCabecera, errorCabecera, cabeceraForm,
            iniciarEdicionCabecera, cancelarEdicionCabecera, guardarCabecera } = usarCabecera(datos, resumen, { guardarYRecargar, pedirConfirmacion });

        const contextoPaneles = { pedirConfirmacion, mostrarAviso, guardarYRecargar };

        // ---------- Documento Contable ----------
        const panelContable = crearPanelDocumento({
            datos, resumen, contexto: contextoPaneles,
            documentoInicial: datos.documentoContable,
            itemsIniciales: datos.itemsDocumentoContable,
            itemVacio: itemContableVacio,
            enriquecerItem: (datos, item) => {
                const tipo = datos.tiposComprobante.find(tc => String(tc.id) === String(item.tipo_comprobante_id));
                return { tipo_comprobante_nombre: tipo ? tipo.nombre : '' };
            },
            validarItem: (datos, item) => validarItemContableCliente(datos.tiposComprobante, item),
            campoFechaForm: 'fecha_rendicion',
            endpoints: {
                guardar: '/guardar-documento-contable',
                subirAdjunto: '/subir-adjunto-comprobante',
                quitarDocumento: '/quitar-documento-contable',
            },
            campoIdAdjunto: 'documento_id',
            requiereEvidencia: true,
            mensajeItemsVacios: 'Agrega al menos un comprobante.',
            mensajes: {
                documentoGuardado: 'Documento Contable guardado.',
                documentoQuitado: 'Documento Contable quitado del Resumen.',
            },
            confirmaciones: {
                quitarItem: { titulo: 'Quitar comprobante', mensaje: '¿Quitar este comprobante de la lista?' },
                quitarDocumento: { titulo: 'Quitar Documento Contable', mensaje: '¿Quitar el Documento Contable de este Resumen? Esta acción no se puede deshacer.' },
            },
        });
        function editarContable() { iniciarEdicionDocumento('contable', panelContable); }

        const { verificandoRuc, verificarRuc, alCambiarRucContable } = usarVerificacionRuc(datos, panelContable.nuevoItem, { mostrarAviso });

        // Documento No Contable
        const panelNoContable = crearPanelDocumento({
            datos, resumen, contexto: contextoPaneles,
            documentoInicial: datos.documentoNoContable,
            itemsIniciales: datos.itemsDocumentoNoContable,
            itemVacio: itemNoContableVacio,
            enriquecerItem: (datos, item) => {
                const tipo = datos.tiposDocumentoNoContable.find(td => String(td.id) === String(item.tipo_documento_id));
                return { tipo_documento_nombre: tipo ? tipo.nombre : '' };
            },
            campoFechaForm: 'fecha_rendicion',
            endpoints: {
                guardar: '/guardar-documento-no-contable',
                subirAdjunto: '/subir-adjunto-no-contable',
                quitarDocumento: '/quitar-documento-no-contable',
            },
            campoIdAdjunto: 'documento_id',
            requiereEvidencia: true,
            mensajeItemsVacios: 'Agrega al menos un item.',
            mensajes: {
                documentoGuardado: 'Documento No Contable guardado.',
                documentoQuitado: 'Documento No Contable quitado del Resumen.',
            },
            confirmaciones: {
                quitarItem: { titulo: 'Quitar item', mensaje: '¿Quitar este item de la lista?' },
                quitarDocumento: { titulo: 'Quitar Documento No Contable', mensaje: '¿Quitar el Documento No Contable de este Resumen? Esta acción no se puede deshacer.' },
            },
        });
        function editarNoContable() { iniciarEdicionDocumento('no_contable', panelNoContable); }

        // ---------- Planilla de Movilidad ----------
        const panelPlanilla = crearPanelDocumento({
            datos, resumen, contexto: contextoPaneles,
            documentoInicial: datos.planilla,
            itemsIniciales: datos.itemsPlanilla,
            itemVacio: itemPlanillaVacio,
            campoFechaForm: 'fecha_emision',
            endpoints: {
                guardar: '/guardar-planilla',
                subirAdjunto: '/subir-adjunto-planilla',
                quitarDocumento: '/quitar-planilla',
            },
            campoIdAdjunto: 'planilla_id',
            requiereEvidencia: false,
            mensajeItemsVacios: 'Agrega al menos un viaje.',
            mensajes: {
                documentoGuardado: 'Planilla de Movilidad guardada. Puedes seguir editándola o enviarla cuando esté lista.',
                documentoQuitado: 'Planilla quitada del Resumen.',
            },
            confirmaciones: {
                quitarItem: { titulo: 'Quitar viaje', mensaje: '¿Quitar este viaje de la lista?' },
                quitarDocumento: { titulo: 'Quitar Planilla de Movilidad', mensaje: '¿Quitar la Planilla de este Resumen? No se borra: queda disponible para enlazarla en otro Resumen.' },
            },
            permiteXml: false
        });

        function editarPlanilla() {
            iniciarEdicionDocumento('planilla', panelPlanilla);
            modoPlanilla.value = 'nueva';
        }

        async function enlazarPlanillaExistente(planillaId) {
            const resultado = await enviarFormulario(datos.baseUrl + '/enlazar-planilla', {
                resumen_id: datos.resumenId, planilla_id: planillaId,
            });
            resultado.ok ? guardarYRecargar('Planilla enlazada al Resumen.') : mostrarAviso(resultado.error);
        }

        // Acordeón + flujo de "+ Añadir documento"
        const tiposFaltantes = computed(() => {
            const todos = [
                { clave: 'contable', etiqueta: 'Documento Contable', existe: !!panelContable.panel.value.documento },
                { clave: 'no_contable', etiqueta: 'Documento No Contable', existe: !!panelNoContable.panel.value.documento },
                { clave: 'planilla', etiqueta: 'Planilla de Movilidad', existe: !!panelPlanilla.panel.value.documento },
            ];
            return todos.filter(t => !t.existe);
        });

        const filasAbiertas = ref(datos.esEditable ? [] : [
            ...(datos.documentoContable ? ['contable'] : []),
            ...(datos.documentoNoContable ? ['no_contable'] : []),
            ...(datos.planilla ? ['planilla'] : []),
        ]);

        function filaEstaAbierta(clave) {
            return filasAbiertas.value.includes(clave);
        }

        function abrirFila(clave) {
            if (!filaEstaAbierta(clave)) {
                filasAbiertas.value.push(clave);
            }
        }

        function cerrarFila(clave) {
            filasAbiertas.value = filasAbiertas.value.filter(
                fila => fila !== clave
            );
        }

        // Colapsa el formulario, no descarta cambios.
        function toggleFila(clave) {
            if (filaEstaAbierta(clave)) cerrarFila(clave);
            else abrirFila(clave);
        }

        const filasDisponibles = computed(() => {
            const disponibles = [];

            if (panelContable.panel.value.documento || panelContable.panel.value.editando) disponibles.push('contable');
            if (panelNoContable.panel.value.documento || panelNoContable.panel.value.editando) disponibles.push('no_contable');
            if (panelPlanilla.panel.value.documento || panelPlanilla.panel.value.editando) disponibles.push('planilla');

            return disponibles;
        });

        const todosDesplegados = computed(() =>
            filasDisponibles.value.length > 0
            && filasDisponibles.value.every(clave => filasAbiertas.value.includes(clave))
        );

        function alternarTodasLasFilas() {
            filasAbiertas.value = todosDesplegados.value
                ? []
                : [...filasDisponibles.value];
        }

        function iniciarAgregarDocumento() {
            if (hayPanelesEnEdicion.value || cabeceraEditando.value || procesando.value) return;
            mostrarSelectorTipo.value = true;
        }

        function elegirTipoNuevo(clave) {
            mostrarSelectorTipo.value = false;
            if (clave === 'contable') iniciarEdicionDocumento(clave, panelContable);
            if (clave === 'no_contable') iniciarEdicionDocumento(clave, panelNoContable);
            if (clave === 'planilla') {
                iniciarEdicionDocumento(clave, panelPlanilla);
                modoPlanilla.value = datos.planillasDisponibles.length > 0 ? null : 'nueva';
            }
        }

        function cancelarEdicionDocumento(panelDocumento, clave, etiqueta) {
            pedirConfirmacion({
                titulo: `Cancelar edición de ${etiqueta}`,
                mensaje: 'Los cambios que todavía no hayas guardado se perderán.',
                textoConfirmar: 'Descartar cambios',
                tipo: 'peligro',
                accion: () => {
                    panelDocumento.cancelar();
                    if (!panelDocumento.panel.value.documento) cerrarFila(clave);
                    if (clave === 'planilla') {
                        modoPlanilla.value = datos.planillasDisponibles.length > 0 ? null : 'nueva';
                    }
                }
            });
        }

        function cancelarContable() {
            cancelarEdicionDocumento(
                panelContable,
                'contable',
                'Documento Contable'
            );
        }

        function cancelarNoContable() {
            cancelarEdicionDocumento(
                panelNoContable,
                'no_contable',
                'Documento No Contable'
            );
        }

        function cancelarPlanilla() {
            cancelarEdicionDocumento(
                panelPlanilla,
                'planilla',
                'Planilla de Movilidad'
            );
        }

        // Vista previa
        const totalGeneral = computed(() => panelContable.total.value + panelNoContable.total.value + panelPlanilla.total.value);
        const saldoRestante = computed(() => Number(resumen.saldo_inicial || 0) - totalGeneral.value);
        const mostrarVistaPrevia = ref(false);
        const refModalVistaPrevia = ref(null);
        const tabVistaPrevia = ref('sistema');

        watch(mostrarVistaPrevia, (abierto) => {
            if (abierto) tabVistaPrevia.value = 'sistema';
            abierto ? refModalVistaPrevia.value?.showModal() : refModalVistaPrevia.value?.close();
        });

        const historialAbierto = ref(false);
        const refModalHistorial = ref(null);
        window.abrirHistorial = function () { historialAbierto.value = true; };
        watch(historialAbierto, (abierto) => { abierto ? refModalHistorial.value?.showModal() : refModalHistorial.value?.close(); });

        const filasVistaPrevia = computed(() => {
            // La primera fila es siempre el ingreso (el fondo con el que
            // arranca el Resumen) — no es un "documento", así que no lleva
            // número: la numeración empieza recién en el primer documento
            // agregado.
            const filas = [{ numero: '-', etiqueta: '-', ingreso: Number(resumen.saldo_inicial || 0), egreso: 0 }];
            let n = 1;
            if (panelContable.panel.value.documento) filas.push({ numero: n++, etiqueta: 'Documento Contable', ingreso: 0, egreso: panelContable.total.value });
            if (panelNoContable.panel.value.documento) filas.push({ numero: n++, etiqueta: 'Documento No Contable', ingreso: 0, egreso: panelNoContable.total.value });
            if (panelPlanilla.panel.value.documento) filas.push({ numero: n++, etiqueta: 'Planilla de Movilidad', ingreso: 0, egreso: panelPlanilla.total.value });
            let saldo = 0;
            filas.forEach(f => { saldo += f.ingreso - f.egreso; f.saldo = saldo; });
            return filas;
        });

        // Enviar / reenviar a aprobación
        const enviando = ref(false);

        function enviarAAprobacion() {
            if (!puedeEnviar.value) return;
            const mensaje = resumenEstado === 'RECHAZADO'
                ? '¿Reenviar este Resumen a aprobación? Ya no podrás editar los documentos hasta que se vuelva a resolver.'
                : '¿Enviar este Resumen a aprobación? Ya no podrás editar los documentos hasta que se apruebe o rechace.';
            pedirConfirmacion({
                titulo: resumenEstado === 'RECHAZADO' ? 'Reenviar a aprobación' : 'Enviar a aprobación',
                mensaje,
                textoConfirmar: resumenEstado === 'RECHAZADO' ? 'Reenviar' : 'Enviar',
                tipo: 'normal',
                accion: async () => {
                    if (enviando.value) return;
                    enviando.value = true;
                    try {
                        const resultado = await enviarFormulario(datos.baseUrl + '/enviar-resumen', { resumen_id: datos.resumenId });
                        if (resultado.ok) { window.location.reload(); return; }
                        mostrarAviso(resultado.error);
                    } finally {
                        enviando.value = false;
                    }
                },
            });
        }

        // Estado global de edición: solo se permite modificar un documento a la vez.
        const documentoEnEdicion = computed(() => {
            if (panelContable.panel.value.editando) return 'contable';
            if (panelNoContable.panel.value.editando) return 'no_contable';
            if (panelPlanilla.panel.value.editando) return 'planilla';

            return null;
        });

        const hayPanelesEnEdicion = computed(() =>
            documentoEnEdicion.value !== null
        );

        const procesando = computed(() =>
            enviando.value
            || guardandoCabecera.value
            || panelContable.panel.value.guardando
            || panelNoContable.panel.value.guardando
            || panelPlanilla.panel.value.guardando
        );

        const puedeEnviar = computed(() =>
            totalGeneral.value > 0
            && !hayPanelesEnEdicion.value
            && !cabeceraEditando.value
            && !procesando.value
        );

        function iniciarEdicionDocumento(clave, panelDocumento) {
            if (cabeceraEditando.value) {
                mostrarAviso('Guarda o cancela la edición de la cabecera antes de editar un documento.');
                return;
            }
            if (documentoEnEdicion.value !== null && documentoEnEdicion.value !== clave) {
                mostrarAviso(
                    'Guarda o cancela el documento que estás editando antes de abrir otro.'
                );
                return;
            }
            abrirFila(clave);
            panelDocumento.editar();
        }

        window.addEventListener('beforeunload', (e) => {
            if ((hayPanelesEnEdicion.value || cabeceraEditando.value) && !salioConGuardado()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        window.confirmarSalidaResumen = function (evento) {
            if (!hayPanelesEnEdicion.value && !cabeceraEditando.value) return;
            evento.preventDefault();
            pedirConfirmacion({
                titulo: 'Salir sin guardar',
                mensaje: 'Tienes cambios sin guardar. Si sales ahora, se pierden. ¿Seguro que quieres salir?',
                textoConfirmar: 'Salir de todas formas',
                tipo: 'peligro',
                accion: () => { window.location.href = datos.baseUrl + '/home'; },
            });
        };

        return {
            datos, resumen, esEditable, resumenEstado, hoy,
            cabeceraEditando, guardandoCabecera, errorCabecera, cabeceraForm,
            iniciarEdicionCabecera, cancelarEdicionCabecera, guardarCabecera,
            enviando, puedeEnviar, enviarAAprobacion,
            hayPanelesEnEdicion, documentoEnEdicion, procesando,
            mostrarSelectorTipo, historialAbierto, refModalHistorial, modoPlanilla, tiposFaltantes,
            iniciarAgregarDocumento, elegirTipoNuevo,
            totalGeneral, saldoRestante, mostrarVistaPrevia, tabVistaPrevia, filasVistaPrevia, refModalVistaPrevia,

            dialogoConfirmar, refDialogoConfirmar, confirmarAccion, cancelarConfirmacion,
            dialogoAviso, refDialogoAviso, cerrarAviso,
            toast,

            contable: panelContable.panel, totalContable: panelContable.total,
            nuevoItemContable: panelContable.nuevoItem, archivoNuevoItemContable: panelContable.archivoNuevoItem,
            refArchivoContable: panelContable.refArchivoItem, modalItemContableAbierto: panelContable.modalItemAbierto,
            xmlNuevoItemContable: panelContable.xmlNuevoItem, refXmlContable: panelContable.refXmlItem,
            refModalItemContable: panelContable.refModalItem, editandoIdxContable: panelContable.editandoIdx,
            mensajeValidacionItemContable: panelContable.mensajeValidacionItem, puedeGuardarItemContable: panelContable.puedeGuardarItem,
            editarContable, cancelarContable,
            abrirModalItemContable: panelContable.abrirModalItem,
            guardarItemContable: panelContable.guardarItem, quitarItemContable: panelContable.quitarItem,
            guardarDocumentoContable: panelContable.guardarDocumento, quitarDocumentoContable: panelContable.quitarDocumento,
            verificandoRuc, verificarRuc, alCambiarRucContable,

            noContable: panelNoContable.panel, totalNoContable: panelNoContable.total,
            nuevoItemNoContable: panelNoContable.nuevoItem, archivoNuevoItemNoContable: panelNoContable.archivoNuevoItem,
            refArchivoNoContable: panelNoContable.refArchivoItem, modalItemNoContableAbierto: panelNoContable.modalItemAbierto,
            xmlNuevoItemNoContable: panelNoContable.xmlNuevoItem, refXmlNoContable: panelNoContable.refXmlItem,
            refModalItemNoContable: panelNoContable.refModalItem, editandoIdxNoContable: panelNoContable.editandoIdx,
            editarNoContable, cancelarNoContable,
            abrirModalItemNoContable: panelNoContable.abrirModalItem,
            guardarItemNoContable: panelNoContable.guardarItem, quitarItemNoContable: panelNoContable.quitarItem,
            guardarDocumentoNoContable: panelNoContable.guardarDocumento, quitarDocumentoNoContable: panelNoContable.quitarDocumento,

            planilla: panelPlanilla.panel, totalPlanilla: panelPlanilla.total,
            nuevoItemPlanilla: panelPlanilla.nuevoItem, archivoNuevoItemPlanilla: panelPlanilla.archivoNuevoItem,
            refArchivoPlanilla: panelPlanilla.refArchivoItem, modalItemPlanillaAbierto: panelPlanilla.modalItemAbierto,
            refModalItemPlanilla: panelPlanilla.refModalItem, editandoIdxPlanilla: panelPlanilla.editandoIdx,
            editarPlanilla, cancelarPlanilla,
            abrirModalItemPlanilla: panelPlanilla.abrirModalItem,
            guardarItemPlanilla: panelPlanilla.guardarItem, quitarItemPlanilla: panelPlanilla.quitarItem,
            guardarPlanilla: panelPlanilla.guardarDocumento, enlazarPlanillaExistente, quitarPlanilla: panelPlanilla.quitarDocumento,

            formatearSoles, formatearFecha, numeroDocumento,

            cancelarModalItemContable: panelContable.cancelarModalItem, cancelarModalItemNoContable: panelNoContable.cancelarModalItem, cancelarModalItemPlanilla: panelPlanilla.cancelarModalItem,

            filasAbiertas, filaEstaAbierta, toggleFila, todosDesplegados, alternarTodasLasFilas
        };
    },
}).mount('#app');
