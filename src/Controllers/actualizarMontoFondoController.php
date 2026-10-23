<?php

require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

header('Content-Type: application/json; charset=utf-8');

if ($_SESSION['rol'] !== 'TESORERIA') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Solo Tesorería puede modificar fondos.']);
    exit;
}

require_once __DIR__ . '/../Negocio/fondos.php';

$resultado = cambiarMontoFondoRecurrente(
    (int)($_POST['fondo_id'] ?? 0),
    $_POST['monto_asignado'] ?? null
);

echo json_encode($resultado);
