<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

header('Content-Type: application/json; charset=utf-8');

if ($_SESSION['rol'] !== 'TESORERIA' && !esAdministrador()) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Acceso no permitido'
    ]);
    exit;
}

require_once __DIR__ . '/../Negocio/pagosBbva.php';

$resultado = obtenerEstadoPagosBbva((int)($_GET['empresa_id'] ?? 0));
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
