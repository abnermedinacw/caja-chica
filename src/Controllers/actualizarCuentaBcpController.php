<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/perfil.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$resultado = actualizarCuentaBcp(
    (int)($_SESSION['trabajador_id'] ?? 0),
    $_POST['tipo_cuenta_bcp'] ?? '',
    $_POST['cuenta_bcp'] ?? '',
    $_POST['cuenta_bcp_confirmacion'] ?? '',
    !empty($_POST['marcar_predeterminado'])
);

$_SESSION['perfil_mensaje'] = $resultado['ok']
    ? ['tipo' => 'exito', 'texto' => $resultado['mensaje']]
    : ['tipo' => 'error', 'texto' => $resultado['error']];

header('Location: ' . BASE_URL . '/perfil');
exit;
