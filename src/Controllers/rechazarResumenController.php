<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/aprobaciones.php';
require_once __DIR__ . '/..//Negocio/notificacionCorreo.php';

$resumenId = (int)($_POST['resumen_id'] ?? 0);
$motivo = trim($_POST['motivo'] ?? '');
$resultado = rechazarResumen($resumenId, (int)$_SESSION['usuario_id'], $_SESSION['rol'], $motivo);

if ($resultado['ok']) {
    notificar($resumenId, 'RECHAZADO');

    header('Location: ' . BASE_URL . '/home');
    exit;
}


http_response_code(400);
echo '<p>' . htmlspecialchars($resultado['error']) . '</p>';
echo '<p><a href="' . BASE_URL . '/home">Volver</a></p>';