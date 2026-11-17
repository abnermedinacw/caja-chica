<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

if (!esAdministrador()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado.']);
    exit;
}

require_once __DIR__ . '/../Negocio/cajaChica.php';

$resultado = anularResumenAdmin(
    (int)($_POST['resumen_id'] ?? 0),
    (int)$_SESSION['usuario_id'],
    (string)($_POST['motivo'] ?? '')
);

if (!$resultado['ok']) {
    http_response_code(422);
}

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
