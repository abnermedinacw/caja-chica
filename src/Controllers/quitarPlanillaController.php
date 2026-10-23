<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/planillaMovilidad.php';

$trabajadorId = $_SESSION['trabajador_id'];
$resultado = quitarPlanillaDeResumen((int)($_POST['resumen_id'] ?? 0), $trabajadorId);

header('Content-type: application/json');
echo json_encode($resultado);
