<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/../Negocio/aprobaciones.php';
require_once __DIR__ . '/../Negocio/exportarPdf.php';

$resumenId = (int)($_GET['id'] ?? 0);
$resumen = obtenerResumenAprobador($resumenId);

if (!$resumen || !puedeVerDocumentosResumen(
    $resumen,
    (int)($_SESSION['trabajador_id'] ?? 0),
    $_SESSION['rol'],
    (int)$_SESSION['usuario_id']
)) {
    http_response_code(403);
    die('No tienes permiso para ver este Resumen.');
}

try {
    $pdf = generarPdfResumen($resumenId);
} catch (RuntimeException $e) {
    http_response_code(422);
    die($e->getMessage());
}
if ($pdf === null) {
    http_response_code(404);
    die('Resumen no encontrado.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="resumen-' . $resumenId . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
