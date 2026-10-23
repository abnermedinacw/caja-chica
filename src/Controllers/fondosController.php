<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

if (empty($_SESSION['trabajador_id'])) {
    http_response_code(403);
    die('Esta sección es solo para trabajadores con fondo asignado');
}

require_once __DIR__ . '/../Datos/fondos.php';
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/../Vistas/helpers.php';
require_once __DIR__ . '/../Vistas/fondosView.php';

$trabajadorId = (int)$_SESSION['trabajador_id'];
$fondos = obtenerFondosTrabajador($trabajadorId);

foreach ($fondos as &$fondo) {
    $fondo['resumenes'] = listarResumenesPorFondo((int)$fondo['fondo_id']);
}
unset($fondo);
vistaFondos($fondos);