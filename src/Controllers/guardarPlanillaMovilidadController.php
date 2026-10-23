<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/planillaMovilidad.php';

$trabajadorId = $_SESSION['trabajador_id'];

$items = json_decode($_POST['items_json'] ?? '[]', true);
if (!is_array($items)) {
    $items = [];
}

$resultado = guardarPlanillaMovilidad($trabajadorId, (int)($_POST['resumen_id'] ?? 0), $_POST, $items);

header('Content-type: application/json');
echo json_encode($resultado);
