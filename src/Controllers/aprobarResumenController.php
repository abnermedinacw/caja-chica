<?php 
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

$resumenId = (int)($_POST['resumen_id'] ?? 0);

try {
    require_once __DIR__ . '/../Negocio/aprobaciones.php';
    require_once __DIR__ . '/../Negocio/notificacionCorreo.php';
    $resultado = aprobarResumen($resumenId, (int)$_SESSION['usuario_id'], $_SESSION['rol']);
} catch (Throwable $e) {
    $rutaLog = __DIR__ . '/../../storage/logs/aprobaciones.txt';
    if (!is_dir(dirname($rutaLog))) @mkdir(dirname($rutaLog), 0775, true);
    $detalle = str_replace(["\r", "\n"], ' ', $e->getMessage());
    $linea = date('Y-m-d H:i:s') . " | resumen_id={$resumenId} | "
        . get_class($e) . ': ' . $detalle . PHP_EOL;
    if (@file_put_contents($rutaLog, $linea, FILE_APPEND | LOCK_EX) === false) {
        error_log('Aprobacion de resumen ' . $resumenId . ': ' . $detalle);
    }

    http_response_code(500);
    echo '<p>No se pudo completar la aprobación. Revisa el registro de aprobaciones.</p>';
    exit;
}

if ($resultado['ok']) {
    notificar($resumenId, 'APROBADO');

    header('Location: ' . BASE_URL . '/home');
    exit;
}

http_response_code(400);
echo '<p>' . htmlspecialchars($resultado['error']) . '</p>';
echo '<p><a href="' . BASE_URL . '/home">Volver</a></p>';
