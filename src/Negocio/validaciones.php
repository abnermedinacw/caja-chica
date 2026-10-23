<?php
// Validaciones compartidas entre los 3 documentos. Los formatos de serie/
// número/RUC siguen las convenciones generales de SUNAT (comprobantes
// electrónicos: letra + 3 dígitos; físicos: 3 dígitos; correlativo numérico
// hasta 8 dígitos; RUC 11 dígitos) — si la empresa maneja un formato distinto,
// ajusta las constantes/regex de este archivo, no la lógica que las llama.

function validarFechaEnRango(string $fecha, string $desde, string $hasta, string $etiqueta): ?string
{
    if ($fecha === '') {
        return "Falta la fecha de $etiqueta.";
    }
    if ($fecha < $desde || $fecha > $hasta) {
        return "La fecha de $etiqueta ($fecha) debe estar entre $desde y $hasta.";
    }
    return null;
}

// Ninguna fecha del sistema puede ser futura (se rinden gastos ya realizados).
function validarFechaNoFutura(string $fecha, string $etiqueta): ?string
{
    if ($fecha > date('Y-m-d')) {
        return "La fecha de $etiqueta no puede ser futura.";
    }
    return null;
}

// Positivo, máximo 2 decimales, y un tope como resguardo ante errores de
// tipeo (un cero de más). Ajusta $tope si tu empresa maneja montos mayores.
function validarMonto($monto, float $tope = 50000): ?string
{
    if (!is_numeric($monto)) {
        return 'El monto no es válido.';
    }
    if ((float)$monto <= 0) {
        return 'El monto debe ser mayor a cero.';
    }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', (string)$monto)) {
        return 'El monto admite máximo 2 decimales.';
    }
    if ((float)$monto > $tope) {
        return "El monto parece demasiado alto (más de $tope). Verifícalo.";
    }
    return null;
}

// Serie del comprobante. Reglas generales SUNAT:
//  - Electrónico: 1 letra + 3 dígitos (F001 factura, B001 boleta, E001 RxH).
//  - Físico (talonario impreso): 3 dígitos (001).
//  - Ticket/Voucher/Otros: no siguen este estándar, se acepta libre.
function validarSerieComprobante(string $serie, string $tipoComprobanteNombre): ?string
{
    $tiposEstrictos = ['FACTURA', 'BOLETA', 'RECIBO POR HONORARIOS'];
    if (!in_array(strtoupper($tipoComprobanteNombre), $tiposEstrictos, true)) {
        return $serie === '' ? 'Indica la serie.' : null;
    }
    if (!preg_match('/^([A-Za-z]\d{3}|\d{3})$/', $serie)) {
        return "Serie inválida para $tipoComprobanteNombre. Formato esperado: letra+3 dígitos (ej. F001) o 3 dígitos (ej. 001).";
    }
    return null;
}

// Número/correlativo: solo dígitos, hasta 8 (estándar SUNAT).
function validarNumeroComprobante(string $numero): ?string
{
    if (!preg_match('/^\d{1,8}$/', $numero)) {
        return 'El número de comprobante debe ser solo dígitos (máximo 8).';
    }
    return null;
}

// RUC opcional (la verificación OpenRUC aún no está conectada), pero si se
// llena, debe ser exactamente 11 dígitos.
function validarRucOpcional(?string $ruc): ?string
{
    if ($ruc === null || $ruc === '') {
        return null;
    }
    if (!preg_match('/^\d{11}$/', $ruc)) {
        return 'El RUC debe tener exactamente 11 dígitos.';
    }
    return null;
}

function validarRucComprobante(?string $ruc, ?bool $proveedorValido, string $tipoComprobanteNombre): ?string
{
    $tiposEstrictos = ['FACTURA', 'BOLETA', 'RECIBO POR HONORARIOS'];
    $esFormal = in_array(strtoupper($tipoComprobanteNombre), $tiposEstrictos, true);

    if ($ruc === null || $ruc === '') {
        return $esFormal ? "$tipoComprobanteNombre requiere el RUC del proveedor." : null;
    }
    if (!preg_match('/^\d{11}$/', $ruc)) {
        return 'El RUC debe tener exactamente 11 dígitos.';
    }
    if ($proveedorValido !== true) {
        return 'Verifica el RUC del proveedor antes de guardar.';
    }
    return null;
}