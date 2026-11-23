export const TIPOS_ESTRICTOS = ['FACTURA', 'BOLETA', 'RECIBO POR HONORARIOS'];

export function itemContableVacio() {
    return {
        concepto: '',
        fecha: '',
        tipo_comprobante_id: '',
        serie: '',
        numero_comprobante: '',
        monto: '',
        proveedor_ruc: '',
        proveedor_razon_social: '',
        proveedor_condicion: '',
        proveedor_estado: '',
        proveedor_valido: null,
        cpe_estado_sunat: '',
        cpe_estado_ruc: '',
        cpe_condicion_ruc: '',
        fecha_verificacion_cpe: '',
    };
}

export function esRucValido(valor) {
    return valor === true || valor === 1 || valor === '1';
}

export function tipoRequiereCpe(tiposComprobante, tipoId) {
    const tipo = (tiposComprobante || []).find(tc => String(tc.id) === String(tipoId));
    return Boolean(tipo && tipo.codigo_sunat);
}

// Regla estricta SUNAT: solo es válido si está ACEPTADO (1), ACTIVO (00) y HABIDO (00).
export function esCpeValido(item) {
    return String(item?.cpe_estado_sunat || '').toUpperCase() === 'ACEPTADO'
        && String(item?.cpe_estado_ruc || '').toUpperCase() === 'ACTIVO'
        && String(item?.cpe_condicion_ruc || '').toUpperCase() === 'HABIDO';
}

function nombreTipoComprobante(tiposComprobante, tipoId) {
    const tipo = tiposComprobante.find(tc => String(tc.id) === String(tipoId));
    return tipo ? tipo.nombre.toUpperCase() : '';
}

export function validarItemContableCliente(tiposComprobante, item) {
    if (!item.concepto || !item.fecha || !item.tipo_comprobante_id || !item.monto) {
        return 'Completa los campos';
    }

    const tipoNombre = nombreTipoComprobante(tiposComprobante, item.tipo_comprobante_id);
    const esFormal = TIPOS_ESTRICTOS.includes(tipoNombre);

    if (!String(item.serie || '').trim()) {
        return 'Indica la serie.';
    }

    if (!/^\d{1,8}$/.test(item.numero_comprobante)) {
        return 'El número de comprobante debe ser solo dígitos (máximo 8).';
    }

    if (esFormal && !item.proveedor_ruc) {
        return `${tipoNombre} requiere el RUC del proveedor.`;
    }
    if (item.proveedor_ruc) {
        if (!/^\d{11}$/.test(item.proveedor_ruc)) {
            return 'El RUC debe tener exactamente 11 dígitos.';
        }
        if (!esRucValido(item.proveedor_valido)) {
            return 'Verifica el RUC del proveedor antes de guardar.';
        }
    }

    if (tipoRequiereCpe(tiposComprobante, item.tipo_comprobante_id) && !esCpeValido(item)) {
        if (!item.cpe_estado_sunat) {
            return 'Verifica el comprobante en SUNAT antes de guardar.';
        }
        return 'El comprobante no cumple la validación SUNAT (debe estar ACEPTADO, ACTIVO y HABIDO).';
    }
    return null;
}
