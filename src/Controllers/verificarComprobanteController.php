<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Negocio/aprobaciones.php';
require_once __DIR__ . '/../Negocio/verificacionCpeSunat.php';
require_once __DIR__ . '/../Negocio/validaciones.php';
require_once __DIR__ . '/../Datos/comprobantes.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

$resumenId = (int)($_POST['resumen_id'] ?? 0);
$trabajadorId = (int)($_SESSION['trabajador_id'] ?? 0);
$usuarioId = (int)$_SESSION['usuario_id'];
$rol = (string)($_SESSION['rol'] ?? '');

// Validamos que quien consulta pueda editar este Resumen (solicitante o Contabilidad en corrección)
// y obtenemos el RUC real de la empresa desde BD para elegir las credenciales SUNAT.
$acceso = resolverAccesoEdicionResumen($resumenId, $trabajadorId, $usuarioId, $rol);
if (!$acceso['ok']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => $acceso['error']]);
    exit;
}

$tipoComprobanteId = (int)($_POST['tipo_comprobante_id'] ?? 0);
$tipoInfo = obtenerTipoComprobantePorId($tipoComprobanteId);
if (!$tipoInfo || empty($tipoInfo['codigo_sunat'])) {
    echo json_encode(['ok' => false, 'error' => 'Este tipo de comprobante no requiere verificación ante SUNAT.']);
    exit;
}

$ruc = trim((string)($_POST['proveedor_ruc'] ?? ''));
$serie = trim((string)($_POST['serie'] ?? ''));
$numero = trim((string)($_POST['numero_comprobante'] ?? ''));
$fecha = trim((string)($_POST['fecha'] ?? ''));
$monto = $_POST['monto'] ?? '';

if (!preg_match('/^\d{11}$/', $ruc)) {
    echo json_encode(['ok' => false, 'error' => 'Ingresa un RUC de proveedor válido de 11 dígitos.']);
    exit;
}
if ($serie === '' || ($errNum = validarNumeroComprobante($numero)) !== null) {
    echo json_encode(['ok' => false, 'error' => $errNum ?? 'Completa la serie y número del comprobante.']);
    exit;
}
if ($fecha === '' || ($errMonto = validarMonto($monto)) !== null) {
    echo json_encode(['ok' => false, 'error' => $errMonto ?? 'Completa la fecha y el monto del comprobante.']);
    exit;
}

$rucEmpresa = (string)($acceso['resumen']['empresa_ruc'] ?? '');
$resultado = consultarComprobanteSunat(
    $rucEmpresa,
    $ruc,
    (string)$tipoInfo['codigo_sunat'],
    $serie,
    $numero,
    $fecha,
    (float)$monto
);

echo json_encode($resultado);
