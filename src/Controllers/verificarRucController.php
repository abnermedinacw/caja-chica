<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/verificacionRuc.php';

$ruc = trim($_GET['ruc'] ?? '');
$resultado = consultarRuc($ruc);

header('Content-Type: application/json');
echo json_encode($resultado);