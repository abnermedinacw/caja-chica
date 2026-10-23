<?php

require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

$puedeGestionarPagos = $_SESSION['rol'] === 'TESORERIA';
$puedeReactivar = esAdministrador();

if (!$puedeGestionarPagos && !$puedeReactivar) {
    http_response_code(403);
    die('No permitido.');
}

require_once __DIR__ . '/../Datos/datosReembolso.php';
require_once __DIR__ . '/../Vistas/pagosView.php';

$empresas = listarEmpresasPagosBbva();
$vistaInicial = $puedeReactivar && (!$puedeGestionarPagos || ($_GET['modo'] ?? '') === 'reactivacion')
    ? 'descargadas'
    : 'pendientes';

vistaPagos($empresas, $puedeGestionarPagos, $puedeReactivar, $vistaInicial);
