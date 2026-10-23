<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/cajaChica.php';

$trabajadorId = $_SESSION['trabajador_id'];
$resumenId = (int)($_POST['resumen_id'] ?? 0);

$resultado = editarCabeceraResumen(
    $resumenId,
    $trabajadorId,
    $_POST['periodo_inicio'] ?? '',
    $_POST['periodo_fin'] ?? '',
    $_POST['fecha_emision'] ?? ''
);

header('Content-type: application/json');
echo json_encode($resultado);
