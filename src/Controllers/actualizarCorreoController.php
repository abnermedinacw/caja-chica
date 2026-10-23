<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/perfil.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$resultado = actualizarCorreoPerfil(
    (int)$_SESSION['usuario_id'],
    (string)($_POST['correo'] ?? ''),
    (string)($_POST['correo_confirmacion'] ?? '')
);

$_SESSION['perfil_mensaje'] = $resultado['ok']
    ? ['tipo' => 'exito', 'texto' => $resultado['mensaje']]
    : ['tipo' => 'error', 'texto' => $resultado['error']];

header('Location: ' . BASE_URL . '/perfil');
exit;
