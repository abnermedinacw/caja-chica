<?php
require_once __DIR__ . '/../Negocio/auth.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'error' => 'Método no permitido.',
    ]);

    exit;
}

requiereSesion();

echo json_encode([
    'ok' => true
]);