const { ref } = Vue;
import { obtenerJson } from '../utils/api.js';

// Verificación de RUC contra SUNAT, usada solo por el item de Documento
// Contable. Recibe el ref reactivo del item que se está editando (el
// mismo que usa el modal) para poder rellenarlo/limpiarlo in place.
export function usarVerificacionRuc(datos, nuevoItem, { mostrarAviso }) {
    const verificandoRuc = ref(false);

    function limpiarDatosProveedor() {
        nuevoItem.value.proveedor_valido = null;
        nuevoItem.value.proveedor_estado = '';
        nuevoItem.value.proveedor_condicion = '';
        nuevoItem.value.proveedor_razon_social = '';
    }

    function alCambiarRucContable() {
        limpiarDatosProveedor();
    }

    async function verificarRuc() {
        const ruc = nuevoItem.value.proveedor_ruc;
        if (!/^\d{11}$/.test(ruc)) {
            mostrarAviso('Escribe un RUC de 11 dígitos antes de verificar.');
            return;
        }
        verificandoRuc.value = true;
        const resultado = await obtenerJson(
            datos.baseUrl + '/verificar-ruc?ruc=' + ruc,
            'No se pudo conectar con el servicio de verificación'
        );
        if (resultado.ok) {
            nuevoItem.value.proveedor_razon_social = resultado.razon_social;
            nuevoItem.value.proveedor_condicion = resultado.condicion;
            nuevoItem.value.proveedor_estado = resultado.estado;
            nuevoItem.value.proveedor_valido = resultado.valido;
            if (!resultado.valido) {
                mostrarAviso(
                    `Este proveedor no está habilidato ante SUNAT. Estado: ${resultado.estado || '-'}, Condición: ${resultado.condicion || '-'}`,
                    'RUC no válido'
                );
            }
        } else {
            limpiarDatosProveedor();
            mostrarAviso(resultado.error);
        }
        verificandoRuc.value = false;
    }

    return { verificandoRuc, verificarRuc, alCambiarRucContable };
}
