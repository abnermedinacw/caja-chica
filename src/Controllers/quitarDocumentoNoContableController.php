<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/documentoNoContable.php';

$trabajadorId = $_SESSION['trabajador_id'];
$resultado = eliminarDocumentoNoContableDeResumen((int)($_POST['resumen_id'] ?? 0), $trabajadorId);

header('Content-type: application/json');
echo json_encode($resultado);
