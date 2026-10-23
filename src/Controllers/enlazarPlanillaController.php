<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/planillaMovilidad.php';

$trabajadorId = $_SESSION['trabajador_id'];
$resultado = enlazarPlanillaExistente($trabajadorId, (int)($_POST['resumen_id'] ?? 0), (int)($_POST['planilla_id'] ?? 0));

header('Content-type: application/json');
echo json_encode($resultado);
