<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/documentoNoContable.php';

$trabajadorId = (int)($_SESSION['trabajador_id'] ?? 0);
$areaId = (int)($_SESSION['area_id'] ?? 0);
$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
$rol = (string)($_SESSION['rol'] ?? '');

$items = json_decode($_POST['items_json'] ?? '[]', true);
if (!is_array($items)) {
    $items = [];
}

$resultado = guardarDocumentoNoContable($trabajadorId, $areaId, (int)($_POST['resumen_id'] ?? 0), $_POST, $items, $usuarioId, $rol);

header('Content-type: application/json');
echo json_encode($resultado);
