<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/adjuntos.php';

$trabajadorId = (int)($_SESSION['trabajador_id'] ?? 0);
$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
$rol = (string)($_SESSION['rol'] ?? '');
$planillaId = (int)($_POST['planilla_id'] ?? 0);
$adjuntoId = (int)($_POST['adjunto_id'] ?? 0);

$resultado = quitarAdjuntoPlanilla($trabajadorId, $planillaId, $adjuntoId, $usuarioId, $rol);

header('Content-type: application/json');
echo json_encode($resultado);