<?php
if (PHP_SAPI !== 'cli') exit;

require_once __DIR__ . '/../src/Negocio/aprobaciones.php';

$pdo = obtenerConexion();
$resumenId = (int)$pdo->query(
    "SELECT id FROM caja_chica
     WHERE nivel_actual_id IS NOT NULL
       AND estado_general IN ('EN_REVISION', 'RECHAZADO')
     ORDER BY id DESC LIMIT 1"
)->fetchColumn();

if ($resumenId === 0) {
    echo "SIN CASO: no hay un resumen pendiente en la base local.\n";
    exit;
}

$resumen = obtenerResumenAprobador($resumenId);
if (!$resumen) throw new RuntimeException('No se pudo leer el resumen de prueba.');

$usuarios = $pdo->query(
    "SELECT id, trabajador_id, rol FROM usuario WHERE estado = 'ACTIVO'"
)->fetchAll();

$ajeno = null;
foreach ($usuarios as $usuario) {
    if ((int)$usuario['trabajador_id'] === (int)$resumen['trabajador_id']) continue;
    if (usuarioParticipo($resumenId, (int)$usuario['id'])) continue;
    if (puedeAtenderNivel($resumen, (int)$usuario['id'], $usuario['rol'])) continue;
    $ajeno = $usuario;
    break;
}

if (!$ajeno) {
    echo "SIN CASO: no hay un usuario ajeno a ese resumen en la base local.\n";
    exit;
}

if (obtenerResumenPorId($resumenId, (int)$ajeno['trabajador_id'])) {
    throw new RuntimeException('El usuario ajeno figura como dueño del resumen.');
}
if (puedeVerDocumentosResumen(
    $resumen,
    (int)$ajeno['trabajador_id'],
    $ajeno['rol'],
    (int)$ajeno['id']
)) {
    throw new RuntimeException('El usuario ajeno puede abrir el PDF o los adjuntos.');
}
if (puedeAtenderNivel($resumen, (int)$ajeno['id'], $ajeno['rol'])) {
    throw new RuntimeException('El usuario ajeno puede atender la aprobación.');
}
if (!puedeVerDocumentosResumen(
    $resumen,
    (int)$resumen['trabajador_id'],
    (string)$resumen['solicitante_rol'],
    (int)$resumen['solicitante_usuario_id']
)) {
    throw new RuntimeException('El solicitante perdió acceso a su resumen.');
}

echo "OK: usuario ajeno denegado; solicitante autorizado.\n";
