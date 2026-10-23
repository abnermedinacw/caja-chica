<?php  
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

$rolesConHistorial = ['CONTABILIDAD', 'GERENCIA', 'TESORERIA'];
if (!in_array($_SESSION['rol'], $rolesConHistorial, true) && empty($_SESSION['es_jefe'])) {
    http_response_code(403);
    die('Esta sección es solo para roles de aprobación.');
}

require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/../Vistas/helpers.php';
require_once __DIR__ . '/../Vistas/historialAprobacionesView.php';

$resumenes = listarResumenesResueltosPorUsuario((int)$_SESSION['usuario_id']);
vistaHistorialAprobaciones($resumenes);
