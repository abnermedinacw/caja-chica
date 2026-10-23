<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/../Datos/comprobantes.php';
require_once __DIR__ . '/../Negocio/aprobaciones.php';
require_once __DIR__ . '/../Negocio/detalleResumen.php';
require_once __DIR__ . '/../Vistas/helpers.php';
require_once __DIR__ . '/../Vistas/verResumenView.php';

$resumenId = (int)($_GET['id'] ?? 0);
$resumen = obtenerResumenAprobador($resumenId);

if (!$resumen) {
    http_response_code(404);
    die('Resumen no encontrado.');
}

$estadosValidos = ['EN_REVISION', 'RECHAZADO'];
if (!in_array($resumen['estado_general'], $estadosValidos, true)
    || !puedeAtenderNivel($resumen, (int)$_SESSION['usuario_id'], $_SESSION['rol'])) {
    http_response_code(403);
    die('Este Resumen no está pendiente de tu aprobación.');
}

$detalle = cargarDetalleResumen($resumenId);
$regreso = resolverRegresoResumen($_GET['origen'] ?? null, 'APROBACION');

vistaVerResumen(
    $resumen,
    $detalle['documentoContable'], $detalle['itemsDocumentoContable'],
    $detalle['documentoNoContable'], $detalle['itemsDocumentoNoContable'],
    $detalle['planilla'], $detalle['itemsPlanilla'], [],
    listarTiposComprobante(), listarTiposDocumentoNoContable(),
    false, obtenerEventosResumen($resumenId), 'APROBACION',
    $regreso,
    [
        'contable' => $detalle['adjuntosSueltosContable'],
        'no_contable' => $detalle['adjuntosSueltosNoContable'],
        'movilidad' => $detalle['adjuntosSueltosPlanilla'],
    ]
);
