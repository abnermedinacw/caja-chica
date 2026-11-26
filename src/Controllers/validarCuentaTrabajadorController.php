<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

header('Content-Type: application/json; charset=utf-8');

if ($_SESSION['rol'] !== 'TESORERIA') {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'No autorizado.',
    ]);
    exit;
}

require_once __DIR__ . '/../Negocio/pagosBbva.php';

$resultado = validarCuentaTrabajadorTesoreria(
    (int)($_POST['trabajador_id'] ?? 0),
    (string)($_POST['banco'] ?? '')
);

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
