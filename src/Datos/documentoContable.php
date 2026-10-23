<?php
require_once __DIR__ . '/../../config/database.php';

function sgteNumeroDocumentoContable(int $trabajadorId): int
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT MAX(numero) AS maximo FROM documento_contable WHERE trabajador_id = ?");
    $stmt->execute([$trabajadorId]);
    $fila = $stmt->fetch();
    return ((int)($fila['maximo'] ?? 0)) + 1;
}

function crearDocumentoContable(
    int $trabajadorId,
    int $areaId,
    int $numero,
    string $periodoInicio,
    string $periodoFin,
    string $fechaRendicion,
    ?int $resumenId
): int
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO documento_contable (numero, trabajador_id, area_id, periodo_inicio, periodo_fin, fecha_rendicion, resumen_id) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$numero, $trabajadorId, $areaId, $periodoInicio, $periodoFin, $fechaRendicion, $resumenId]);
    return (int)$pdo->lastInsertId();
}

function obtenerDocumentoContablePorId(int $id, int $trabajadorId): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT * FROM documento_contable WHERE id = ? AND trabajador_id = ?");
    $stmt->execute([$id, $trabajadorId]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

// Sin filtro de trabajador_id a propósito: la usa el flujo de descarga de
// adjuntos, donde quien pide el archivo puede ser un aprobador (otro
// trabajador, o incluso un rol sin trabajador_id como Tesorería), no
// necesariamente el dueño del documento. La autorización real la hace
// puedeVerDocumentosResumen() después, usando el resumen_id que devuelve.
function obtenerDocumentoContableParaDescarga(int $id): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT * FROM documento_contable WHERE id = ?");
    $stmt->execute([$id]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function obtenerDocumentoContablePorResumen(int $resumenId): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT id, numero, periodo_inicio, periodo_fin, fecha_rendicion FROM documento_contable WHERE resumen_id = ?"
    );
    $stmt->execute([$resumenId]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function enlazarDocumentoContableAResumen(int $documentoId, int $resumenId): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("UPDATE documento_contable SET resumen_id = ? WHERE id = ?");
    $stmt->execute([$resumenId, $documentoId]);
}

function obtenerDetalleDocumentoContable(int $documentoId): array
{
    $pdo = obtenerConexion();
    $sql =
        "SELECT 
            dcd.id, 
            dcd.item,
            dcd.fecha,
            dcd.concepto,
            dcd.monto,
            c.tipo_comprobante_id,
            tc.nombre AS tipo_comprobante,
            c.serie,
            c.numero AS numero_comprobante,
            c.proveedor_ruc,
            c.proveedor_razon_social,
            c.proveedor_estado_sunat AS proveedor_estado,
            c.proveedor_condicion_sunat AS proveedor_condicion,
            c.proveedor_valido
        FROM documento_contable_detalle dcd
        LEFT JOIN  comprobante c ON c.id = dcd.comprobante_id
        LEFT JOIN tipo_comprobante tc ON tc.id = c.tipo_comprobante_id
        WHERE dcd.documento_contable_id = ?
        ORDER BY dcd.item";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$documentoId]);
    return $stmt->fetchAll();
}

function obtenerDetallesIdsDocumentoContable(int $documentoId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT id FROM documento_contable_detalle WHERE documento_contable_id = ?");
    $stmt->execute([$documentoId]);
    return array_column($stmt->fetchAll(), 'id');
}

function insertarDetalleDocumentoContable(
    int $documentoId,
    int $item, 
    string $fecha, 
    string $concepto,
    float $monto,
    ?int $comprobanteId
): int
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO documento_contable_detalle (documento_contable_id, item, fecha, concepto, monto, comprobante_id)
        VALUES (?,?,?,?,?,?)"
    );
    $stmt->execute([$documentoId, $item, $fecha, $concepto, $monto, $comprobanteId]);
    return (int)$pdo->lastInsertId();
}

function actualizarDetalleDocumentoContable(int $detalleId, int $item, string $fecha, string $concepto, float $monto, ?int $comprobanteId): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "UPDATE documento_contable_detalle SET item = ?, fecha = ?, concepto = ?, monto = ?, comprobante_id = ? WHERE id = ?"
    );
    $stmt->execute([$item, $fecha, $concepto, $monto, $comprobanteId, $detalleId]);
}

function eliminarDetalleDocumentoContablePorId(int $detalleId): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("DELETE FROM documento_contable_detalle WHERE id = ?");
    $stmt->execute([$detalleId]);
}

function obtenerTotalDocumentoContable(int $documentoId): float
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT IFNULL(SUM(monto),0) AS total FROM documento_contable_detalle WHERE documento_contable_id = ?");
    $stmt->execute([$documentoId]);
    return (float)$stmt->fetch()['total'];
}

function obtenerAdjuntosDocumentoContable(int $documentoId): array
{
    $pdo = obtenerConexion();
    // Nota: sin "ruta" a propósito — esa ruta de disco no debe llegar nunca
    // al navegador. Para descargar, el cliente solo maneja el "id" del
    // adjunto; es el servidor (descargarController.php) quien resuelve la
    // ruta real, después de verificar que el documento es del trabajador.
    $stmt = $pdo->prepare(
        "SELECT id, nombre_archivo, detalle_id, tipo_archivo
         FROM adjunto_documento_contable
         WHERE documento_contable_id = ?
         ORDER BY fecha_subida"
    );
    $stmt->execute([$documentoId]);
    return $stmt->fetchAll();
}

function eliminarDocumentoContablePorId(int $id): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("DELETE FROM documento_contable WHERE id = ?");
    $stmt->execute([$id]);
}

function obtenerDocumentosContablesTrabajador(int $trabajadorId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT
            dc.id,
            dc.numero,
            dc.fecha_rendicion AS fecha,
            dc.resumen_id,
            cc.numero AS resumen_numero,
            IFNULL ((SELECT SUM(monto) FROM documento_contable_detalle WHERE documento_contable_id = dc.id), 0) AS total
        FROM documento_contable dc
        LEFT JOIN caja_chica cc ON cc.id = dc.resumen_id
        WHERE dc.trabajador_id = ?
        ORDER BY dc.fecha_rendicion DESC"
    );
    $stmt->execute([$trabajadorId]);
    return $stmt->fetchAll();
}
