export function formatearFecha(fecha) {
    if (!fecha) return '-';
    const partes = fecha.split(' ')[0].split('-');
    return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : fecha;
}
export function formatearSoles(monto) {
    return 'S/ ' + parseFloat(monto || 0).toFixed(2);
}

// Mismo formato de presentación que numeroDocumento() en Vistas/helpers.php.
export function numeroDocumento(numero) {
    return String(numero).padStart(3, '0');
}
