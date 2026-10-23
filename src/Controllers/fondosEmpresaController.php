<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

$rolesPermitidos = ['TESORERIA', 'GERENCIA', 'CONTABILIDAD'];
$esJefe = !empty($_SESSION['es_jefe']);
if (!in_array($_SESSION['rol'], $rolesPermitidos, true) && !$esJefe) {
    http_response_code(403);
    die('Esta sección es solo para roles de administración de fondos.');
}

require_once __DIR__ . '/../Datos/fondos.php';
require_once __DIR__ . '/../Datos/trabajadores.php';
require_once __DIR__ . '/../Datos/empresas.php';
require_once __DIR__ . '/../Datos/cargos.php';
require_once __DIR__ . '/../Negocio/fondos.php';
require_once __DIR__ . '/../Vistas/helpers.php';
require_once __DIR__ . '/../Vistas/fondosEmpresaView.php';

// Solo Tesorería asigna; Gerencia/Contabilidad/Jefatura entran de solo
// lectura a la misma pantalla.
$puedeAsignar = $_SESSION['rol'] === 'TESORERIA';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$puedeAsignar) {
        http_response_code(403);
        die('Solo Tesorería puede asignar fondos.');
    }
    $resultado = asignarFondo(
        (int)($_POST['empresa_id'] ?? 0),
        (int)($_POST['trabajador_id'] ?? 0),
        $_POST['tipo'] ?? '',
        $_POST['monto_asignado'] ?? '',
        (int)$_SESSION['usuario_id'],
        $_POST['fecha_asignacion'] ?? ''
    );

    header('Content-type: application/json');
    echo json_encode($resultado);
    exit;
}

$fondos = listarFondos();

if ($esJefe && !in_array($_SESSION['rol'], $rolesPermitidos, true)) {
    $idsACargo = listarIdsTrabajadoresDeArea((int)$_SESSION['area_id']);
    $fondos = array_values(array_filter($fondos, fn($f) => in_array((int)$f['trabajador_id'], $idsACargo, true)));
}

$empresas = $puedeAsignar ? listarEmpresas() : [];
$trabajadores = $puedeAsignar ? listarTrabajadores() : [];

vistaFondosEmpresa($fondos, $empresas, $trabajadores, $puedeAsignar);
