<?php  
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

if (!puedeVerHistorialAprobaciones()) {
    http_response_code(403);
    die('Esta sección es solo para roles de aprobación.');
}

require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/../Vistas/helpers.php';
require_once __DIR__ . '/../Vistas/historialAprobacionesView.php';

$resumenes = listarResumenesResueltosPorUsuario((int)$_SESSION['usuario_id']);
vistaHistorialAprobaciones($resumenes);
