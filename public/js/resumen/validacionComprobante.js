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
    };
}

export function esRucValido(valor) {
    return valor === true || valor === 1 || valor === '1';
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

    if (esFormal) {
        if (!/^([A-Za-z]\d{3}|\d{3})$/.test(item.serie)) {
            return `Serie inválida para ${tipoNombre}. Formato esperado: letra + 3 dígitos (ej. F001) o 3 dítigos (ej. 001).`;
        }
    } else if (item.serie === '') {
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
    return null;
}