<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/adjuntos.php';

$trabajadorId = (int) $_SESSION['trabajador_id'];
$usuarioId = (int) $_SESSION['usuario_id'];
$planillaId = (int)($_POST['planilla_id'] ?? 0);
$detalleId = (int)($_POST['detalle_id'] ?? 0);
$tipoArchivo = $_POST['tipo_archivo'] ?? TIPO_ADJUNTO_EVIDENCIA;

header('Content-type: application/json');

if (empty($_FILES['archivo']) || ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['ok' => false, 'error' => 'Selecciona un archivo.']);
    exit;
}

$resultado = subirAdjuntoPlanilla($trabajadorId, $usuarioId, $planillaId, $detalleId, $_FILES['archivo'], $tipoArchivo);
echo json_encode($resultado);
