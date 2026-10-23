<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

if ($_SESSION['rol'] !== 'TESORERIA') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'ok' => false,
        'error' => 'No autorizado'
    ]);
    exit;
}

require_once __DIR__ . '/../Negocio/pagosBbva.php';
require_once __DIR__ . '/respuestaDescargaBbva.php';

$resultado = generarTxtDesembolsoFondosBbva((int)($_POST['empresa_id'] ?? 0), $_POST['fondos'] ?? []);
responderDescargaBbva($resultado);
