<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

header('Content-Type: application/json; charset=utf-8');

if (!esAdministrador()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No tienes permisos para buscar responsables.']);
    exit;
}

require_once __DIR__ . '/../Datos/usuarios.php';

$busqueda = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$empresaId = (int)($_GET['empresa_id'] ?? 0);
$excluirDni = is_string($_GET['dni'] ?? null) ? trim($_GET['dni']) : '';

if (mb_strlen($busqueda) < 2) {
    echo json_encode(['ok' => true, 'responsables' => []]);
    exit;
}

$responsables = buscarResponsablesActivos($busqueda, $empresaId, $excluirDni);
echo json_encode(['ok' => true, 'responsables' => $responsables], JSON_UNESCAPED_UNICODE);
