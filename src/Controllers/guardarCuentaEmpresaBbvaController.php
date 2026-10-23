<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

header('Content-Type: application/json; charset=utf-8');

if ($_SESSION['rol'] !== 'TESORERIA') {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'No autorizado.'
    ]);
    exit;
}

require_once __DIR__ . '/../Negocio/pagosBbva.php';
$resultado = guardarCuentaEmpresaBbva(
    (int)($_POST['empresa_id'] ?? 0),
    $_POST['cuenta'] ?? '',
    $_POST['confirmacion_cuenta'] ?? ''
);

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
