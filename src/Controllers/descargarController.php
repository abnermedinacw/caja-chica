<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Datos/adjuntos.php';
require_once __DIR__ . '/../Datos/documentoContable.php';
require_once __DIR__ . '/../Datos/documentoNoContable.php';
require_once __DIR__ . '/../Datos/planillaMovilidad.php';
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/../Negocio/aprobaciones.php';

$tipo = $_GET['tipo'] ?? '';
$documentoId = (int)($_GET['id'] ?? 0);
$adjuntoId = (int)($_GET['adjunto'] ?? 0);

if ($documentoId <= 0 || $adjuntoId <= 0) {
    http_response_code(400);
    die('Solicitud inválida');
}

// A diferencia de subir/quitar un adjunto (que sí exige ser el dueño del
// documento), acá el que pide el archivo puede ser un aprobador revisando
// el Resumen de otro trabajador — por eso la búsqueda del documento ya no
// filtra por trabajador_id, y la autorización real se hace después, contra
// el Resumen completo (puedeVerDocumentosResumen).
switch ($tipo) {
    case 'contable':
        $documento = obtenerDocumentoContableParaDescarga($documentoId);
        $adjunto = $documento ? obtenerAdjuntoDocumentoContablePorId($adjuntoId, $documentoId) : null;
        break;
    case 'no_contable':
        $documento = obtenerDocumentoNoContableParaDescarga($documentoId);
        $adjunto = $documento ? obtenerAdjuntoDocumentoNoContablePorId($adjuntoId, $documentoId) : null;
        break;
    case 'movilidad':
        $documento = obtenerPlanillaParaDescarga($documentoId);
        $adjunto = $documento ? obtenerAdjuntoMovilidadPorId($adjuntoId, $documentoId) : null;
        break;
    default:
        http_response_code(404);
        die('Tipo de documento inválido');
}

if (!$documento) {
    http_response_code(404);
    die('Documento no encontrado.');
}

$resumen = obtenerResumenAprobador((int)$documento['resumen_id']);
if (!$resumen || !puedeVerDocumentosResumen($resumen, (int)($_SESSION['trabajador_id'] ?? 0), $_SESSION['rol'], (int)$_SESSION['usuario_id'])) {
    http_response_code(403);
    die('No tienes acceso a este archivo.');
}

if (!$adjunto) {
    http_response_code(404);
    die('Archivo no encontrado.');
}

$rutaCompleta = __DIR__ . '/../../storage/uploads/' . $adjunto['ruta'];
if (!is_file($rutaCompleta)) {
    http_response_code(404);
    die('El archivo ya no existe en el servidor');
}

$tiposMime = [
    'pdf' => 'application/pdf',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'xml' => 'application/xml',
];

$extension = strtolower(pathinfo($rutaCompleta, PATHINFO_EXTENSION));
$mime = $tiposMime[$extension] ?? 'application/octet-stream';

header('Content-type: ' . $mime);
header('Content-Disposition: inline; filename="'. basename($adjunto['nombre_archivo']) .'"');
header('Content-Length: ' . filesize($rutaCompleta));
readfile($rutaCompleta);
exit;