<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

if (($_SESSION['rol'] ?? '') !== 'TESORERIA') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Solo Tesorería puede anular fondos.']);
    exit;
}

require_once __DIR__ . '/../Negocio/fondos.php';

$resultado = anularFondoTesoreria((int)($_POST['fondo_id'] ?? 0));

if (!$resultado['ok']) {
    http_response_code(422);
}

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
