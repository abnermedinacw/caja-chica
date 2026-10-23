<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/perfil.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$resultado = actualizarCuentaBbva((int)($_SESSION['trabajador_id'] ?? 0), $_POST['cuenta_bbva'] ?? '', $_POST['cuenta_bbva_confirmacion'] ?? '');

$_SESSION['perfil_mensaje'] = $resultado['ok'] ? ['tipo' => 'exito', 'texto' => $resultado['mensaje']] : ['tipo' => 'error', 'texto' => $resultado['error']];

header('Location: ' . BASE_URL . '/perfil');
exit;