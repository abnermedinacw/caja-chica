<?php
// Prueba de concurrencia en una BD temporal. No envía correos ni modifica cajas reales.
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/../src/Negocio/cajaChica.php';

function comprobar(bool $condicion, string $mensaje): void
{
    if (!$condicion) throw new RuntimeException($mensaje);
    echo "OK: {$mensaje}\n";
}

if (($argv[1] ?? '') === 'worker') {
    if (!preg_match('/^qa_caja_chica_[a-f0-9]{8}$/', $argv[2] ?? '')) exit(1);
    $pdo = obtenerConexion();
    $pdo->exec('USE `' . $argv[2] . '`');
    echo "LISTO\n";
    fflush(STDOUT);
    $resultado = $argv[6] === 'aprobar'
        ? aprobarResumen((int)$argv[3], (int)$argv[5], $argv[7])
        : enviarResumenAprobacion((int)$argv[3], (int)$argv[4], (int)$argv[5]);
    echo json_encode($resultado + ['transaccion_abierta' => $pdo->inTransaction()]), "\n";
    exit;
}

function enviarDosVeces(string $base, int $resumenId, int $trabajadorId, int $usuarioId, string $operacion = 'enviar', string $rol = ''): void
{
    $pdo = obtenerConexion();
    $pdo->beginTransaction();
    bloquearResumen($resumenId);
    $procesos = [];
    for ($i = 0; $i < 2; $i++) {
        $proceso = proc_open(
            [PHP_BINARY, __FILE__, 'worker', $base, $resumenId, $trabajadorId, $usuarioId, $operacion, $rol],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($proceso)) throw new RuntimeException('No se pudo iniciar el proceso de prueba.');
        fclose($pipes[0]);
        if (trim((string)fgets($pipes[1])) !== 'LISTO') throw new RuntimeException('No se inició el worker.');
        $procesos[] = [$proceso, $pipes];
    }
    $pdo->commit();
    $exitos = 0;
    foreach ($procesos as [$proceso, $pipes]) {
        $salida = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($proceso) !== 0) throw new RuntimeException($error ?: $salida);
        $resultado = json_decode($salida, true, 512, JSON_THROW_ON_ERROR);
        comprobar(!$resultado['transaccion_abierta'], 'La petición libera su transacción');
        $exitos += (int)$resultado['ok'];
    }
    comprobar($exitos === 1, "Dos peticiones de {$operacion}: solo una se procesa");
}

$pdo = obtenerConexion();
$original = $pdo->query('SELECT DATABASE()')->fetchColumn();
$base = 'qa_caja_chica_' . bin2hex(random_bytes(4));
$creada = false;
try {
    $tablas = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);
    $pdo->exec("CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $creada = true;
    $pdo->exec("USE `{$base}`");
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tablas as $tabla) {
        $nombre = str_replace('`', '``', $tabla);
        $estructura = $pdo->query("SHOW CREATE TABLE `{$original}`.`{$nombre}`")->fetch(PDO::FETCH_NUM)[1];
        $pdo->exec($estructura);
    }
    foreach (['empresa', 'area', 'cargo', 'empresa_cliente', 'trabajador', 'usuario', 'nivel_aprobacion'] as $tabla) {
        $pdo->exec("INSERT INTO `{$tabla}` SELECT * FROM `{$original}`.`{$tabla}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    comprobar(obtenerNivelesSinResponsable() === [], 'La prueba tiene los encargados configurados');
    $solicitante = $pdo->query("SELECT t.id AS trabajador_id, u.id AS usuario_id, t.empresa_id
        FROM trabajador t JOIN usuario u ON u.trabajador_id = t.id
        WHERE t.es_jefe = 0 AND u.estado = 'ACTIVO' AND u.rol = 'SOLICITANTE'
        AND EXISTS (SELECT 1 FROM trabajador jefe JOIN usuario uj ON uj.trabajador_id = jefe.id
            WHERE jefe.area_id = t.area_id AND jefe.es_jefe = 1 AND uj.estado = 'ACTIVO') LIMIT 1")->fetch();
    comprobar((bool)$solicitante, 'La prueba tiene un solicitante con jefe');
    $trabajadorId = (int)$solicitante['trabajador_id'];
    $usuarioId = (int)$solicitante['usuario_id'];
    $resumenId = crearBorradorResumen($trabajadorId, (int)$solicitante['empresa_id'], null, 1, date('Y-m-d'), date('Y-m-d'), date('Y-m-d'), 10, 0);
    $pdo->exec("UPDATE caja_chica SET total_egresos = 10 WHERE id = {$resumenId}");
    enviarDosVeces($base, $resumenId, $trabajadorId, $usuarioId);
    comprobar((int)$pdo->query("SELECT COUNT(*) FROM evento_caja_chica WHERE evento = 'ENVIADO'")->fetchColumn() === 1, 'Un solo evento ENVIADO');

    $resumen = obtenerResumenAprobador($resumenId);
    $encargado = (int)$resumen['nivel_actual_aprobador_id'];
    enviarDosVeces($base, $resumenId, $trabajadorId, $encargado, 'aprobar', $resumen['nivel_actual_rol']);
    comprobar((int)$pdo->query("SELECT COUNT(*) FROM evento_caja_chica WHERE evento = 'APROBADO'")->fetchColumn() === 1, 'Una sola aprobación para el nivel');

    actualizarEstadoResumen($resumenId, 'RECHAZADO', null, 'Prueba');
    enviarDosVeces($base, $resumenId, $trabajadorId, $usuarioId);
    comprobar((int)$pdo->query("SELECT COUNT(*) FROM evento_caja_chica WHERE evento = 'ENVIADO'")->fetchColumn() === 2, 'El reenvío registra exactamente un evento nuevo');

    $nivelId = (int)obtenerPrimerNivel()['id'];
    actualizarEstadoResumen($resumenId, 'RECHAZADO', $nivelId, 'En revisión');
    comprobar(!enviarResumenAprobacion($resumenId, $trabajadorId, $usuarioId)['ok'], 'No permite reenviar mientras se revisa el rechazo');
    comprobar(!$pdo->inTransaction(), 'Una validación fallida libera el bloqueo');

    actualizarEstadoResumen($resumenId, 'BORRADOR', null);
    $pdo->exec("CREATE TRIGGER qa_fallo BEFORE UPDATE ON caja_chica FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo provocado por prueba'");
    $fallo = false;
    try {
        enviarResumenAprobacion($resumenId, $trabajadorId, $usuarioId);
    } catch (PDOException $e) {
        $fallo = true;
    }
    comprobar($fallo && !$pdo->inTransaction(), 'Un fallo durante el avance revierte y libera la transacción');
    comprobar((int)$pdo->query("SELECT COUNT(*) FROM evento_caja_chica WHERE evento = 'ENVIADO'")->fetchColumn() === 2, 'El fallo también revierte el evento ENVIADO');
    comprobar(obtenerResumenAprobador($resumenId)['estado_general'] === 'BORRADOR', 'El fallo conserva el estado anterior');
    comprobar(abs(strtotime($pdo->query('SELECT NOW()')->fetchColumn()) - time()) <= 2, 'PHP y MySQL tienen la misma hora de Lima');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $pdo->exec('USE `' . str_replace('`', '``', $original) . '`');
    if ($creada) $pdo->exec("DROP DATABASE `{$base}`");
}
