<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

if (!esAdministrador()) {
    http_response_code(403);
    die('Solo administradores pueden gestionar trabajadores.');
}

require_once __DIR__ . '/../Datos/trabajadores.php';
require_once __DIR__ . '/../Datos/empresas.php';
require_once __DIR__ . '/../Datos/areas.php';
require_once __DIR__ . '/../Vistas/helpers.php';
require_once __DIR__ . '/../Vistas/trabajadoresView.php';

$empresas = listarEmpresas();
$trabajadores = listarTrabajadores();

vistaTrabajadores($trabajadores, $empresas);
