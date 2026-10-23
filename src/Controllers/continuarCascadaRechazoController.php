<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/aprobaciones.php';
require_once __DIR__ . '/../Negocio/notificacionCorreo.php';

$resumenId = (int)($_POST['resumen_id'] ?? 0);
$resultado = continuarCascadaRechazo($resumenId, (int)$_SESSION['usuario_id'], $_SESSION['rol']);

if ($resultado['ok']) {
    notificar($resumenId, 'RECHAZO_REVISADO');

    header('Location: ' . BASE_URL . '/home');
    exit;
}
http_response_code(400);
echo '<p>' . htmlspecialchars($resultado['error']) . '</p>';
echo '<p><a href="' . BASE_URL . '/home">Volver</a></p>';
