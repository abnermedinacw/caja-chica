const { ref, computed, watch } = Vue;
import { enviarFormulario } from '../utils/api.js';

const archivosPendientes = {};
let siguienteTempId = 1;
function nuevoTempId() {
    return 'tmp_' + (siguienteTempId++);
}

export function crearPanelDocumento(opciones) {
    const {
        datos, resumen, contexto,
        documentoInicial, itemsIniciales,
        itemVacio, enriquecerItem,   validarItem,
        campoFechaForm,
        endpoints, campoIdAdjunto,
        requiereEvidencia = false,
        mensajeItemsVacios, mensajes, confirmaciones, permiteXml = true,
    } = opciones;
    const { pedirConfirmacion, mostrarAviso, guardarYRecargar } = contexto;

    function estadoInicial() {
        return {
            documento: documentoInicial,
            periodoInicio: documentoInicial ? documentoInicial.periodo_inicio : resumen.periodo_inicio,
            periodoFin: documentoInicial ? documentoInicial.periodo_fin : resumen.periodo_fin,
            fecha: documentoInicial ? documentoInicial[campoFechaForm] : resumen.fecha_emision,
            items: (itemsIniciales || []).map(it => ({ ...it })),
            error: null, guardando: false, editando: false,
        };
    }
    const panel = ref(estadoInicial());
    const total = computed(() => panel.value.items.reduce((a, it) => a + parseFloat(it.monto || 0), 0));

    function editar() { panel.value.editando = true; }
    function cancelar() { panel.value = estadoInicial(); }

    // Modal de item (añadir/editar uno) 
    const modalItemAbierto = ref(false);
    const refModalItem = ref(null);
    const nuevoItem = ref(itemVacio());
    const archivoNuevoItem = ref(null);
    const refArchivoItem = ref(null);
    const xmlNuevoItem = ref(null);
    const refXmlItem = ref(null);
    const editandoIdx = ref(null); // null = añadiendo; número = editando ese índice
    const estadoItemAlAbrir = ref('');

    function serializarItemActual() {
        return JSON.stringify(nuevoItem.value);
    }

    watch(modalItemAbierto, (abierto) => { abierto ? refModalItem.value?.showModal() : refModalItem.value?.close(); });

    function abrirModalItem(idx = null) {
        if (idx !== null) {
            // Se copia campo por campo (según la forma de itemVacio()) en
            // vez de clonar el item tal cual: así el form del modal nunca
            // arrastra propiedades internas del item (_tempId, adjuntos...).
            const it = panel.value.items[idx];
            const vacio = itemVacio();
            const copia = {};
            Object.keys(vacio).forEach(campo => { copia[campo] = it[campo] ?? vacio[campo]; });
            nuevoItem.value = copia;
            editandoIdx.value = idx;
        } else {
            nuevoItem.value = itemVacio();
            editandoIdx.value = null;
        }
        estadoItemAlAbrir.value = serializarItemActual();
        modalItemAbierto.value = true;
    }

    function cerrarModalItem() {
        nuevoItem.value = itemVacio();
        archivoNuevoItem.value = null;
        xmlNuevoItem.value = null;
        if (refArchivoItem.value) refArchivoItem.value.value = '';
        if (refXmlItem.value) refXmlItem.value.value = '';
        editandoIdx.value = null;
        estadoItemAlAbrir.value = '';
        modalItemAbierto.value = false;
    }

    function cancelarModalItem() {
        const cambioFormulario = serializarItemActual() !== estadoItemAlAbrir.value;
        const hayArchivoSeleccionado = archivoNuevoItem.value !== null || (permiteXml && xmlNuevoItem.value !== null);

        if (!cambioFormulario && !hayArchivoSeleccionado) {
            cerrarModalItem();
            return;
        }
        pedirConfirmacion({
            titulo: 'Descartar cambios',
            mensaje: 'Los datos ingresados en este ítem se perderán.',
            textoConfirmar: 'Descartar',
            tipo: 'peligro',
            accion: cerrarModalItem,
        });
    }

    const mensajeValidacionItem = computed(() => validarItem ? validarItem(datos, nuevoItem.value) : null);
    const puedeGuardarItem = computed(() => mensajeValidacionItem.value === null);

    // guarda o actualia un item
    function guardarItem() {
        if (mensajeValidacionItem.value) return;

        const idx = editandoIdx.value; // Reconoce si se está insertando o editando
        const base = idx !== null ? panel.value.items[idx] : {}; // trae lo que ya existía si se estaba editando
        const pendientesPrevios = base._tempId ? archivosPendientes[base._tempId] : null;
        const yaTieneEvidencia = (base.adjuntos || []).some(
            adjunto => adjunto.tipo_archivo === 'EVIDENCIA'
        ) || Boolean(pendientesPrevios?.evidencia);

        if (requiereEvidencia && !archivoNuevoItem.value && !yaTieneEvidencia) {
            mostrarAviso('Adjunta el sustento antes de añadir este ítem.');
            return;
        }

        const extra = enriquecerItem ? enriquecerItem(datos, nuevoItem.value) : {};
        const item = { ...base, ...nuevoItem.value, ...extra };

        if (archivoNuevoItem.value || (permiteXml && xmlNuevoItem.value)) {
            const tempId = base._tempId || nuevoTempId();
            const archivos = archivosPendientes[tempId] || {};

            if (archivoNuevoItem.value) {
                archivos.evidencia = archivoNuevoItem.value;
                item._archivoPendienteNombre = archivoNuevoItem.value.name;
            }

            if (permiteXml && xmlNuevoItem.value) {
                archivos.xml = xmlNuevoItem.value;
            }

            archivosPendientes[tempId] = archivos;
            item._tempId = tempId;
        }
        if (idx !== null) panel.value.items.splice(idx, 1, item);
        else panel.value.items.push(item);
        cerrarModalItem();
    }

    function quitarItem(idx) {
        pedirConfirmacion({
            ...confirmaciones.quitarItem,
            textoConfirmar: 'Quitar',
            tipo: 'peligro',
            accion: () => { panel.value.items.splice(idx, 1); },
        });
    }

    // Adjuntos por item ya guardado en servidor 
    async function subirArchivoAdjunto(documentoId, detalleId, archivo, tipoArchivo) {
        return enviarFormulario(datos.baseUrl + endpoints.subirAdjunto, {
            [campoIdAdjunto]: documentoId,
            detalle_id: detalleId,
            tipo_archivo: tipoArchivo,
            archivo,
        });
    }

    // Guardar / quitar el documento completo
    async function persistir() {
        if (panel.value.items.length === 0) return { ok: false, error: mensajeItemsVacios };

        const resultado = await enviarFormulario(datos.baseUrl + endpoints.guardar, {
            resumen_id: datos.resumenId,
            periodo_inicio: panel.value.periodoInicio,
            periodo_fin: panel.value.periodoFin,
            [campoFechaForm]: panel.value.fecha,
            items_json: JSON.stringify(panel.value.items),
        });
        if (!resultado.ok) return resultado;

        // El servidor correlaciona cada _tempId con el id real que le tocó
        // (ver Negocio/*.php) — se empareja por esa clave, no por posición
        // en el array, así que no importa en qué orden vuelva la respuesta.
        const idsPorTempId = resultado.idsPorTempId || {};
        for (const item of panel.value.items) {
            const tempId = item._tempId;
            const archivos = tempId && archivosPendientes[tempId];
            const idReal = item.id || (tempId && idsPorTempId[tempId]);
            if (idReal) item.id = idReal;
            if (archivos && !idReal) {
                return {
                    ok: false,
                    error: 'El documento se guardó, pero no se pudieron asociar los archivos a su ítem.',
                };
            }
            if (archivos && idReal) {
                for (const [clave, archivo] of Object.entries(archivos)) {
                    const tipoArchivo = clave === 'xml' ? 'XML' : 'EVIDENCIA';
                    const subida = await subirArchivoAdjunto(resultado.id, idReal, archivo, tipoArchivo);

                    if (!subida.ok) {
                        return {
                            ok: false,
                            error: 'El documento se guardó, pero no se pudo adjuntar '
                                + archivo.name + ': ' + (subida.error || 'intenta de nuevo.'),
                        };
                    }

                    delete archivos[clave];
                }

                if (Object.keys(archivos).length === 0) delete archivosPendientes[tempId];
            }
        }
        return resultado;
    }

    async function guardarDocumento() {
        panel.value.error = null;
        panel.value.guardando = true;
        const resultado = await persistir();
        panel.value.guardando = false;
        if (resultado.ok) { guardarYRecargar(mensajes.documentoGuardado); return; }
        panel.value.error = resultado.error;
        mostrarAviso(resultado.error || 'No se pudo guardar el documento.');
    }

    function quitarDocumento() {
        pedirConfirmacion({
            ...confirmaciones.quitarDocumento,
            textoConfirmar: 'Quitar',
            tipo: 'peligro',
            accion: async () => {
                const resultado = await enviarFormulario(datos.baseUrl + endpoints.quitarDocumento, {
                    resumen_id: datos.resumenId,
                });
                resultado.ok ? guardarYRecargar(mensajes.documentoQuitado) : mostrarAviso(resultado.error);
            },
        });
    }

    return {
        panel, total, editar, cancelar,
        modalItemAbierto, refModalItem, nuevoItem,
        archivoNuevoItem, refArchivoItem, xmlNuevoItem, refXmlItem, editandoIdx,
        abrirModalItem, mensajeValidacionItem, puedeGuardarItem,
        guardarItem, quitarItem, persistir, guardarDocumento,
        quitarDocumento, cancelarModalItem
    };
}
