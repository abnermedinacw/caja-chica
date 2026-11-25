const { ref, computed } = Vue;
import { enviarFormulario } from '../utils/api.js';
import { tipoRequiereCpe, esCpeValido } from './validacionComprobante.js';

// Verificación de Comprobante de Pago Electrónico (CPE) contra la API oficial de SUNAT.
// Trabaja directamente sobre el ref reactivo `nuevoItem` del modal de Documento Contable.
export function usarVerificacionCpe(datos, nuevoItem, { mostrarAviso }) {
    const verificandoCpe = ref(false);
    const observacionesCpe = ref([]);

    const requiereVerificacionCpe = computed(() =>
        tipoRequiereCpe(datos.tiposComprobante, nuevoItem.value.tipo_comprobante_id)
    );

    const cpeEsValido = computed(() => esCpeValido(nuevoItem.value));

    function limpiarVerificacionCpe() {
        nuevoItem.value.cpe_estado_sunat = '';
        nuevoItem.value.cpe_estado_ruc = '';
        nuevoItem.value.cpe_condicion_ruc = '';
        nuevoItem.value.fecha_verificacion_cpe = '';
        nuevoItem.value.cpe_error = '';
        observacionesCpe.value = [];
    }

    // Si cambia cualquiera de los 6 datos consultados en SUNAT, invalidamos la verificación previa.
    function alCambiarDatosCpe() {
        limpiarVerificacionCpe();
    }

    async function verificarComprobante() {
        const it = nuevoItem.value;
        if (!requiereVerificacionCpe.value) return;

        if (!it.fecha || !String(it.serie || '').trim() || !/^\d{1,8}$/.test(String(it.numero_comprobante || ''))
            || !it.monto || !/^\d{11}$/.test(String(it.proveedor_ruc || ''))) {
            mostrarAviso('Completa fecha, tipo, serie, número, monto y RUC (11 dígitos) antes de verificar en SUNAT.');
            return;
        }

        verificandoCpe.value = true;
        observacionesCpe.value = [];
        try {
            const resultado = await enviarFormulario(datos.baseUrl + '/verificar-comprobante', {
                resumen_id: datos.resumenId,
                tipo_comprobante_id: it.tipo_comprobante_id,
                proveedor_ruc: it.proveedor_ruc,
                serie: it.serie,
                numero_comprobante: it.numero_comprobante,
                fecha: it.fecha,
                monto: it.monto,
            });

            if (!resultado.ok) {
                limpiarVerificacionCpe();
                nuevoItem.value.cpe_error = resultado.error || 'No se pudo verificar el comprobante en SUNAT.';
                mostrarAviso(nuevoItem.value.cpe_error);
                return;
            }

            nuevoItem.value.cpe_estado_sunat = resultado.cpe_estado_sunat || '';
            nuevoItem.value.cpe_estado_ruc = resultado.cpe_estado_ruc || '';
            nuevoItem.value.cpe_condicion_ruc = resultado.cpe_condicion_ruc || '';
            nuevoItem.value.fecha_verificacion_cpe = resultado.fecha_verificacion_cpe || '';
            nuevoItem.value.cpe_error = '';
            observacionesCpe.value = Array.isArray(resultado.observaciones) ? resultado.observaciones : [];

            if (!resultado.valido) {
                mostrarAviso(
                    `Comprobante no válido ante SUNAT. Comprobante: ${resultado.cpe_estado_sunat || '-'} · RUC: ${resultado.cpe_estado_ruc || '-'} · Domicilio: ${resultado.cpe_condicion_ruc || '-'}.`,
                    'Comprobante rechazado'
                );
            }
        } finally {
            verificandoCpe.value = false;
        }
    }

    return {
        verificandoCpe,
        observacionesCpe,
        requiereVerificacionCpe,
        cpeEsValido,
        limpiarVerificacionCpe,
        alCambiarDatosCpe,
        verificarComprobante,
    };
}
