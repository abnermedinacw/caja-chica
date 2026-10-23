<?php
require_once __DIR__ . '/../Datos/fondos.php';
require_once __DIR__ . '/../Datos/trabajadores.php';
require_once __DIR__ . '/../Datos/empresas.php';

function asignarFondo(int $empresaId, int $trabajadorId, string $tipo, float $montoAsignado, int $asignadoPor, string $fechaAsignacion): array
{
    if (!obtenerEmpresaPorId($empresaId)) {
        return ['ok' => false, 'error' => 'Selecciona una empresa válida.'];
    }
    if (!obtenerTrabajadorPorId($trabajadorId)) {
        return ['ok' => false, 'error' => 'Selecciona un trabajador válido.'];
    }
    if (!in_array($tipo, ['RECURRENTE', 'NO_RECURRENTE'], true)) {
        return ['ok' => false, 'error' => 'Selecciona un tipo de fondo válido.'];
    }
    if (!is_numeric($montoAsignado) || (float)$montoAsignado <= 0) {
        return ['ok' => false, 'error' => 'El monto debe ser mayor a cero.'];
    }
    if ($fechaAsignacion === '' || $fechaAsignacion > date('Y-m-d')) {
        return ['ok' => false, 'error' => 'La fecha de asignación no puede estar vacía ni ser futura.'];
    }
    if (tieneFondoRecurrenteActivo($empresaId, $trabajadorId)) {
        return ['ok' => false, 'error' => 'El trabajador ya tiene un fondo recurrente activo en esta empresa.'];
    }

    $fondoId = crearFondo($empresaId, $trabajadorId, $tipo, (float)$montoAsignado, $asignadoPor, $fechaAsignacion);
    return ['ok' => true, 'id' => $fondoId];
}

function cambiarMontoFondoRecurrente(int $fondoId, mixed $montoNuevo): array
{
    if ($fondoId <= 0 || !is_numeric($montoNuevo)) {
        return ['ok' => false, 'error' => 'Ingresa un monto válido.'];
    }

    $montoNuevo = round((float)$montoNuevo, 2);
    if ($montoNuevo <= 0) {
        return ['ok' => false, 'error' => 'El monto debe ser mayor a cero.'];
    }

    $fondo = obtenerFondoPorId($fondoId);
    if (!$fondo) {
        return ['ok' => false, 'error' => 'Fondo no encontrado.'];
    }
    if ($fondo['tipo'] !== 'RECURRENTE') {
        return ['ok' => false, 'error' => 'Solo se puede modificar el monto de fondos recurrentes.'];
    }
    if ($fondo['estado'] !== 'ACTIVO') {
        return ['ok' => false, 'error' => 'Solo se puede modificar un fondo activo.'];
    }

    $comprometido = round((float)$fondo['monto_comprometido'], 2);
    if ($montoNuevo < $comprometido) {
        return [
            'ok' => false,
            'error' => 'El monto no puede ser menor a S/ ' . number_format($comprometido, 2)
                . ', porque ese importe ya está comprometido en resúmenes.',
        ];
    }

    actualizarMontoAsignadoFondo($fondoId, $montoNuevo);
    return ['ok' => true];
}
