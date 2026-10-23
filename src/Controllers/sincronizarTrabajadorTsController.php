<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

if (!esAdministrador()) {
    http_response_code(403);
    header('Content-type: application/json');
    echo json_encode(['ok' => false, 'error' => 'No tienes permisos para registrar trabajadores.']);
    exit;
}

require_once __DIR__ . '/../Negocio/trabajadoresTs.php';

$resultado = sincronizarTrabajador($_POST, (int)$_SESSION['usuario_id']); // recibimos POST de front
header('Content-type: application/json; charset=utf-8');
echo json_encode($resultado);
