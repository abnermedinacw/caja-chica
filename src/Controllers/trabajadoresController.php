<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

if (!esAdministrador()) {
    http_response_code(403);
    die('Solo administradores pueden gestionar trabajadores.');
}

require_once __DIR__ . '/../Datos/trabajadores.php';
require_once __DIR__ . '/../Datos/usuarios.php';
require_once __DIR__ . '/../Datos/empresas.php';
require_once __DIR__ . '/../Datos/areas.php';
require_once __DIR__ . '/../Vistas/helpers.php';
require_once __DIR__ . '/../Vistas/trabajadoresView.php';

$busqueda = is_string($_GET['buscar'] ?? null) ? mb_substr(trim($_GET['buscar']), 0, 100) : '';
$empresaId = filter_var($_GET['empresa_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: 0;
$pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$porPagina = filter_var($_GET['por_pagina'] ?? 25, FILTER_VALIDATE_INT);
if (!in_array($porPagina, [10, 25, 50], true)) $porPagina = 25;

$total = contarTrabajadores($busqueda, $empresaId);
$totalPaginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($pagina, $totalPaginas);
$desplazamiento = ($pagina - 1) * $porPagina;
$trabajadores = listarTrabajadores($busqueda, $empresaId, $porPagina, $desplazamiento);
$paginacion = [
    'pagina' => $pagina,
    'por_pagina' => $porPagina,
    'total' => $total,
    'total_paginas' => $totalPaginas,
    'buscar' => $busqueda,
    'empresa_id' => $empresaId,
];

$empresas = listarEmpresas();
$responsables = listarResponsablesActivos();
$areas = listarAreas();

vistaTrabajadores($trabajadores, $empresas, $responsables, $areas, $paginacion);
