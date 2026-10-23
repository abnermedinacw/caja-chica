<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/firmas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (empty($_FILES['firma'])) {
    $resultado = ['ok' => false, 'error' => 'Selecciona una firma.'];
} else {
    $resultado = guardarFirmaPersonal((int)$_SESSION['usuario_id'], $_FILES['firma']);
}

$_SESSION['perfil_mensaje'] = $resultado['ok'] 
    ? [
        'tipo' => 'exito',
        'texto' => 'La firma se guardó correctamente.'
    ] 
    : [
        'tipo' => 'error',
        'texto' => $resultado['error']
    ];

header('Location: ' . BASE_URL . '/perfil');
exit;