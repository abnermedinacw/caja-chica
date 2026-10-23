<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

if (empty($_SESSION['trabajador_id'])) {
    http_response_code(403);
    die('Error');
}
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/../Vistas/helpers.php';
require_once __DIR__ . '/../Vistas/historialResumenesView.php';

$resumenes = listarResumenesPorTrabajador((int)$_SESSION['trabajador_id']);
vistaHistorialResumenes($resumenes);