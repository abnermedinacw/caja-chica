<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Datos/fondos.php';
require_once __DIR__ . '/../Datos/empresas.php';
require_once __DIR__ . '/../Negocio/cajaChica.php';
require_once __DIR__ . '/../Vistas/crearResumenView.php';

$trabajadorId = $_SESSION['trabajador_id'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // fondo_id llega vacío ('') cuando el usuario elige rendir sin fondo
    // asignado — a diferencia de los demás campos, "" no es un valor
    // inválido acá, así que se traduce a null explícitamente en vez de
    // dejar que (int)'' se convierta en 0 (que sí sería un id inválido).
    $fondoIdPost = $_POST['fondo_id'] ?? '';
    $fondoId = $fondoIdPost === '' ? null : (int)$fondoIdPost;

    $resultado = crearResumen(
        $trabajadorId,
        (int)$_SESSION['usuario_id'],
        (int)($_POST['empresa_id'] ?? 0),
        $fondoId,
        $_POST['periodo_inicio'] ?? '',
        $_POST['periodo_fin'] ?? '',
        $_POST['fecha_emision'] ?? ''
    );

    header('Content-type: application/json');
    if ($resultado['ok']) {
        echo json_encode(['ok' => true, 'redirect' => BASE_URL . '/ver-resumen?id=' . $resultado['id']]);
    } else {
        echo json_encode(['ok' => false, 'error' => $resultado['error']]);
    }
    exit;
}

$fondos = obtenerFondosActivos($trabajadorId);
$empresas = listarEmpresas();
vistaCrearResumen($fondos, $empresas, $error);