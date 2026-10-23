<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Datos/firmas.php';
require_once __DIR__ . '/../Negocio/firmas.php';

$firma = obtenerFirmaPersonal((int)$_SESSION['usuario_id']);
$ruta = obtenerRutaAbsolutaFirma($firma['ruta_imagen'] ?? null);

if ($ruta === null) {
    http_response_code(404);
    die('Firma no encontrada.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($ruta);

if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
    http_response_code(415);
    die('Formato no permitido.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: private, max-age=300');

readfile($ruta);
exit;