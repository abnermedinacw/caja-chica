<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/adjuntos.php';

$trabajadorId = $_SESSION['trabajador_id'];
$planillaId = (int)($_POST['planilla_id'] ?? 0);
$adjuntoId = (int)($_POST['adjunto_id'] ?? 0);

$resultado = quitarAdjuntoPlanilla($trabajadorId, $planillaId, $adjuntoId);

header('Content-type: application/json');
echo json_encode($resultado);