<?php
require_once __DIR__ . '/../../config/database.php';

function obtenerPlanillaPorId(int $id, int $trabajadorId): ?array
{
    $pdo = obtenerConexion();

    $sql = 
    "SELECT 
        pm.*,
        t.nombres,
        t.dni,
        a.nombre AS area_nombre
    FROM planilla_movilidad pm
    JOIN trabajador t ON t.id = pm.trabajador_id
    JOIN area a ON a.id = t.area_id
    WHERE pm.id = ? AND pm.trabajador_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $trabajadorId]);
    $doc = $stmt->fetch();
    return $doc ?: null;
}

// Ver obtenerDocumentoContableParaDescarga(): mismo motivo, sin filtro de
// trabajador_id porque quien descarga puede ser un aprobador.
function obtenerPlanillaParaDescarga(int $id): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT * FROM planilla_movilidad WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    return $doc ?: null;
}

function obtenerPlanillaPorResumen(int $resumenId): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT id, numero, periodo_inicio, periodo_fin, fecha_emision, total FROM planilla_movilidad WHERE resumen_id = ?");
    $stmt->execute([$resumenId]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function obtenerDetallePlanilla(int $planillaId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT 
            id,
	        item, 
            fecha, 
            motivo, 
            origen, 
            destino, 
            monto, 
            tipo_evidencia,
            observacion_evidencia, 
            validado
        FROM planilla_movilidad_detalle
        WHERE planilla_id = ?
        ORDER BY item"
    );
    $stmt->execute([$planillaId]);
    return $stmt->fetchAll();
}

function obtenerAdjuntosPlanilla(int $planillaId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT 
            id,
            nombre_archivo,
            detalle_id,
            tipo_archivo
        FROM adjunto_movilidad
        WHERE planilla_id = ?
        ORDER BY fecha_subida"
    );
    $stmt->execute([$planillaId]);
    return $stmt->fetchAll();
}

// Planillas disponibles para enlazar, osea que no estén en alguna otra caja chica
function listarPlanillasDisponibles(int $trabajadorId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT id, numero, total, fecha_emision
        FROM planilla_movilidad
        WHERE trabajador_id = ? AND resumen_id IS NULL
        ORDER BY numero DESC"
    );
    $stmt->execute([$trabajadorId]);
    return $stmt->fetchAll();
}

function sgteNumeroPlanillaMovilidad(int $trabajadorId): int
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT MAX(numero) AS maximo FROM planilla_movilidad WHERE trabajador_id = ?");
    $stmt->execute([$trabajadorId]);
    $fila = $stmt->fetch();
    return ((int)($fila['maximo'] ?? 0)) + 1;
}

function crearPlanillaBorrador(int $trabajadorId, int $numero, string $periodoInicio, string $periodoFin, string $fechaEmision, ?int $resumenId): int
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO planilla_movilidad (numero, trabajador_id, resumen_id, periodo_inicio, periodo_fin, fecha_emision)
        VALUES(?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$numero, $trabajadorId, $resumenId, $periodoInicio, $periodoFin, $fechaEmision]);
    return (int)$pdo->lastInsertId();
}

function enlazarPlanillaAResumen(int $planillaId, int $resumenId): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("UPDATE planilla_movilidad SET resumen_id = ? WHERE id = ?");
    $stmt->execute([$resumenId, $planillaId]);
}

function actualizarCabeceraPlanilla(int $id, string $periodoInicio, string $periodoFin, string $fechaEmision): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "UPDATE planilla_movilidad SET periodo_inicio = ?, periodo_fin = ?, fecha_emision = ? WHERE id = ?"
    );
    $stmt->execute([$periodoInicio, $periodoFin, $fechaEmision, $id]);
}

function desenlazarPlanilla(int $planillaId): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("UPDATE planilla_movilidad SET resumen_id = NULL WHERE id = ?");
    $stmt->execute([$planillaId]);
}

function obtenerDetallesIdsPlanilla(int $planillaId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT id FROM planilla_movilidad_detalle WHERE planilla_id = ?");
    $stmt->execute([$planillaId]);
    return array_column($stmt->fetchAll(), 'id');
}

function insertarDetallePlanilla(
    int $planillaId, int $item, string $fecha, string $motivo, string $origen, string $destino,
    float $monto, string $tipoEvidencia, ?string $observacionEvidencia
): int {
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO planilla_movilidad_detalle
            (planilla_id, item, fecha, motivo, origen, destino, monto, tipo_evidencia, observacion_evidencia)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$planillaId, $item, $fecha, $motivo, $origen, $destino, $monto, $tipoEvidencia, $observacionEvidencia]);
    return (int)$pdo->lastInsertId();
}

function actualizarDetallePlanilla(
    int $detalleId, int $item, string $fecha, string $motivo, string $origen, string $destino,
    float $monto, string $tipoEvidencia, ?string $observacionEvidencia
): void {
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "UPDATE planilla_movilidad_detalle
        SET item = ?, fecha = ?, motivo = ?, origen = ?, destino = ?, monto = ?, tipo_evidencia = ?, observacion_evidencia = ?
        WHERE id = ?"
    );
    $stmt->execute([$item, $fecha, $motivo, $origen, $destino, $monto, $tipoEvidencia, $observacionEvidencia, $detalleId]);
}

function eliminarDetallePlanillaPorId(int $detalleId): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("DELETE FROM planilla_movilidad_detalle WHERE id = ?");
    $stmt->execute([$detalleId]);
}

function recalcularTotalPlanilla(int $planillaId): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "UPDATE planilla_movilidad
        SET total = (SELECT IFNULL(SUM(monto), 0) FROM planilla_movilidad_detalle WHERE planilla_id = ?)
        WHERE id = ?"
    );
    $stmt->execute([$planillaId, $planillaId]);
}

// Contabilidad valida cada item (evidencia Yape/Plin/transferencia)
function validarDetallePlanilla(int $detalleId, int $validadoPor): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "UPDATE planilla_movilidad_detalle SET validado = 1, validado_por = ?, fecha_validacion = NOW() WHERE id = ?"
    );
    $stmt->execute([$validadoPor, $detalleId]);
}

function obtenerPlanillaTrabajador(int $trabajadorId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT
            pm.id,
            pm.numero,
            pm.fecha_emision AS fecha,
            pm.resumen_id,
            cc.numero AS resumen_numero,
            pm.total
        FROM planilla_movilidad pm
        LEFT JOIN caja_chica cc ON cc.id = pm.resumen_id
        WHERE pm.trabajador_id = ?
        ORDER BY pm.fecha_emision DESC, pm.id DESC"
    );
    $stmt->execute([$trabajadorId]);
    return $stmt->fetchAll();
}
