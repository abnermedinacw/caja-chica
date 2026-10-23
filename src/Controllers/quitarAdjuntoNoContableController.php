<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/adjuntos.php';

$trabajadorId = $_SESSION['trabajador_id'];
$documentoId = (int)($_POST['documento_id'] ?? 0);
$adjuntoId = (int)($_POST['adjunto_id'] ?? 0);

$resultado = quitarAdjuntoDocumentoNoContable($trabajadorId, $documentoId, $adjuntoId);

header('Content-type: application/json');
echo json_encode($resultado);