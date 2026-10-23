<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/aprobaciones.php';
require_once __DIR__ . '/../Negocio/detalleResumen.php';
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/../Datos/comprobantes.php';
require_once __DIR__ . '/../Vistas/helpers.php';
require_once __DIR__ . '/../Vistas/verResumenView.php';

$trabajadorId = (int)($_SESSION['trabajador_id'] ?? 0);
$usuarioId = (int)$_SESSION['usuario_id'];
$resumenId = (int)($_GET['id'] ?? 0);

$resumen = obtenerResumenPorId($resumenId, $trabajadorId);
$esPropietario = $resumen !== null;

if (!$esPropietario) {
    $resumen = obtenerResumenAprobador($resumenId);
    if (!$resumen || !usuarioParticipo($resumenId, $usuarioId)) {
        http_response_code(403);
        die('No tienes permiso para ver este Resumen.');
    }
}

$detalle = cargarDetalleResumen($resumenId);
$esEditable = $esPropietario && esResumenEditable($resumen);
$regreso = resolverRegresoResumen($_GET['origen'] ?? null, 'LECTURA');

vistaVerResumen(
    $resumen,
    $detalle['documentoContable'], $detalle['itemsDocumentoContable'],
    $detalle['documentoNoContable'], $detalle['itemsDocumentoNoContable'],
    $detalle['planilla'], $detalle['itemsPlanilla'],
    $esPropietario ? listarPlanillasDisponibles($trabajadorId) : [],
    listarTiposComprobante(), listarTiposDocumentoNoContable(),
    $esEditable, obtenerEventosResumen($resumenId),
    $esEditable ? 'EDICION' : 'LECTURA',
    $regreso,
    [
        'contable' => $detalle['adjuntosSueltosContable'],
        'no_contable' => $detalle['adjuntosSueltosNoContable'],
        'movilidad' => $detalle['adjuntosSueltosPlanilla'],
    ]
);
