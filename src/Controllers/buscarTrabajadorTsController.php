<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

if (!esAdministrador()) {
    http_response_code(403);
    header('Content-type: application/json');
    echo json_encode(['ok' => false, 'error' => 'No tienes permisos para registrar trabajadores']);
    exit;
}

require_once __DIR__ . '/../Negocio/trabajadoresTs.php';

$dni = trim($_POST['dni'] ?? $_GET['dni']);
$resultado = buscarTrabajadorTs($dni);

header('Content-type: application/json');
echo json_encode($resultado);
