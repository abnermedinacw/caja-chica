<?php

require_once __DIR__ . '/../Negocio/auth.php';

requiereSesion();

require_once __DIR__ . '/../Datos/fondos.php';
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/../Datos/cargos.php';
require_once __DIR__ . '/../Vistas/helpers.php';
require_once __DIR__ . '/../Vistas/homeView.php';
require_once __DIR__ . '/../Vistas/bandejaAprobadorView.php';

$rol = $_SESSION['rol'];
$esJefe = !empty($_SESSION['es_jefe']);
$esAprobadorPorRol = in_array($rol, ['CONTABILIDAD', 'GERENCIA', 'TESORERIA'], true);

if (!$esAprobadorPorRol && !$esJefe) {
    $trabajadorId = $_SESSION['trabajador_id'];
    $fondos = obtenerFondosActivos($trabajadorId);
    $resumenes = listarResumenesPorTrabajador($trabajadorId, 8);
    vistaHome($fondos, $resumenes);
    exit;
} 

$resumenesBandeja = [];
if ($esAprobadorPorRol) {
    $resumenesBandeja = listarResumenesPorUsuario((int)$_SESSION['usuario_id'], $rol);
}
if ($esJefe) {
    $resumenesBandeja = array_merge(
        $resumenesBandeja,
        listarResumenesJefaturas((int)$_SESSION['area_id'])
    );
}

$misFondos = [];
$misResumenes = [];
if (!empty($_SESSION['trabajador_id'])) {
    $trabajadorId = (int)$_SESSION['trabajador_id'];
    $misFondos = obtenerFondosActivos($trabajadorId);
    $misResumenes = listarResumenesPorTrabajador($trabajadorId, 8);
}

vistaBandejaAprobador($rol, $resumenesBandeja, !empty($_SESSION['trabajador_id']), $misFondos, $misResumenes);
