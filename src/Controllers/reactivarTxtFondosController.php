<?php

require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

header('Content-Type: application/json; charset=utf-8');

if (!esAdministrador()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado.']);
    exit;
}

require_once __DIR__ . '/../Negocio/pagosBbva.php';

$resultado = reactivarDesembolsoFondosBbva(
    (int) ($_POST['empresa_id'] ?? 0),
    $_POST['fondos'] ?? []
);

if (!$resultado['ok']) {
    http_response_code(422);
}

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
