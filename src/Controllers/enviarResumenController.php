<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/cajaChica.php';

$trabajadorId = $_SESSION['trabajador_id'];
$usuarioId = $_SESSION['usuario_id'];
$resumenId = (int)($_POST['resumen_id'] ?? 0);

try {
    $resultado = enviarResumenAprobacion($resumenId, $trabajadorId, $usuarioId);
} catch (Throwable $e) {
    error_log('Envio de resumen ' . $resumenId . ': ' . $e->getMessage());
    http_response_code(500);
    $resultado = ['ok' => false, 'error' => 'No se pudo completar el envío. Inténtalo de nuevo.'];
}

if ($resultado['ok']) {
    require_once __DIR__ . '/../Negocio/notificacionCorreo.php';
    notificar($resumenId, 'ENVIADO');
}

header('Content-type: application/json');
echo json_encode($resultado);
