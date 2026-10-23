<?php

require_once __DIR__ . '/../../config/database.php';

function sgteNumeroDocumentoNoContable(int $trabajadorId): int
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT MAX(numero) AS maximo FROM documento_no_contable WHERE trabajador_id = ?");
    $stmt->execute([$trabajadorId]);
    $fila = $stmt->fetch();
    return ((int)($fila['maximo'] ?? 0)) + 1;
}

function crearDocumentoNoContable(
    int $trabajadorId,
    int $areaId, 
    int $numero,
    string $periodoInicio,
    string $periodoFin,
    string $fechaRendicion,
    int $resumenId
): int
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO documento_no_contable (numero, trabajador_id, area_id, periodo_inicio, periodo_fin, fecha_rendicion, resumen_id)
        VALUES (?,?,?,?,?,?,?)"
    );
    $stmt->execute([$numero, $trabajadorId, $areaId, $periodoInicio, $periodoFin, $fechaRendicion, $resumenId]);
    return (int)$pdo->lastInsertId();
}

function obtenerDocumentoNoContablePorResumen(int $resumenId): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT id, numero, periodo_inicio, periodo_fin, fecha_rendicion FROM documento_no_contable WHERE resumen_id = ?"
    );
    $stmt->execute([$resumenId]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function obtenerDocumentoNoContablePorId(int $id, int $trabajadorId): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT * FROM documento_no_contable WHERE id = ? AND trabajador_id = ?"
    );
    $stmt->execute([$id, $trabajadorId]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

// Ver obtenerDocumentoContableParaDescarga(): mismo motivo, sin filtro de
// trabajador_id porque quien descarga puede ser un aprobador.
function obtenerDocumentoNoContableParaDescarga(int $id): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT * FROM documento_no_contable WHERE id = ?");
    $stmt->execute([$id]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function obtenerDetalleDocumentoNoContable(int $documentoId): array
{
    $pdo = obtenerConexion();
    $sql =
        "SELECT
            dncd.id, dncd.item, dncd.fecha_documento, dncd.descripcion, dncd.monto,
            dncd.numero_paginas, dncd.observaciones, dncd.tipo_documento_id,
            td.nombre AS tipo_documento_nombre
        FROM documento_no_contable_detalle dncd
        JOIN tipo_documento_no_contable td ON td.id = dncd.tipo_documento_id
        WHERE dncd.documento_no_contable_id = ?
        ORDER BY dncd.item";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$documentoId]);
    return $stmt->fetchAll();
}

function obtenerDetallesIdsDocumentoNoContable(int $documentoId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT id FROM documento_no_contable_detalle WHERE documento_no_contable_id = ?");
    $stmt->execute([$documentoId]);
    return array_column($stmt->fetchAll(), 'id');
} 

function insertarDetalleDocumentoNoContable(
    int $documentoId, int $item, int $tipoDocumentoId, string $descripcion,
    string $fechaDocumento, ?int $numeroPaginas, ?string $observaciones, float $monto
): int {
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO documento_no_contable_detalle
            (documento_no_contable_id, item, tipo_documento_id, descripcion, fecha_documento, numero_paginas, observaciones, monto)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$documentoId, $item, $tipoDocumentoId, $descripcion, $fechaDocumento, $numeroPaginas, $observaciones, $monto]);
    return (int)$pdo->lastInsertId();
}

function actualizarDetalleDocumentoNoContable(
    int $detalleId, int $item, int $tipoDocumentoId, string $descripcion,
    string $fechaDocumento, ?int $numeroPaginas, ?string $observaciones, float $monto
): void {
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "UPDATE documento_no_contable_detalle
        SET item = ?, tipo_documento_id = ?, descripcion = ?, fecha_documento = ?, numero_paginas = ?, observaciones = ?, monto = ?
        WHERE id = ?"
    );
    $stmt->execute([$item, $tipoDocumentoId, $descripcion, $fechaDocumento, $numeroPaginas, $observaciones, $monto, $detalleId]);
}

function eliminarDetalleDocumentoNoContablePorId(int $detalleId): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("DELETE FROM documento_no_contable_detalle WHERE id = ?");
    $stmt->execute([$detalleId]);
}

function obtenerTotalDocumentoNoContable(int $documentoId): float
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT IFNULL(SUM(monto),0) AS total FROM documento_no_contable_detalle WHERE documento_no_contable_id = ?");
    $stmt->execute([$documentoId]);
    return (float)$stmt->fetch()['total'];
}

function listarTiposDocumentoNoContable(): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->query("SELECT id, nombre FROM tipo_documento_no_contable ORDER BY id");
    return $stmt->fetchAll();
}

function obtenerAdjuntosDocumentoNoContable(int $documentoId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT id, nombre_archivo, detalle_id, tipo_archivo
         FROM adjunto_documento_no_contable
         WHERE documento_no_contable_id = ?
         ORDER BY fecha_subida"
    );
    $stmt->execute([$documentoId]);
    return $stmt->fetchAll();
}

function eliminarDocumentoNoContablePorId(int $id): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("DELETE FROM documento_no_contable WHERE id = ?");
    $stmt->execute([$id]);
}

function obtenerDocumentosNoContablesTrabajador(int $trabajadorId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT
            dnc.id,
            dnc.numero,
            dnc.fecha_rendicion AS fecha,
            dnc.resumen_id,
            cc.numero AS resumen_numero,
            IFNULL((SELECT SUM(monto) FROM documento_no_contable_detalle WHERE documento_no_contable_id = dnc.id),0) AS total 
        FROM documento_no_contable dnc
        LEFT JOIN caja_chica cc ON cc.id = dnc.resumen_id
        WHERE dnc.trabajador_id = ?
        ORDER BY dnc.fecha_rendicion DESC"
    );
    $stmt->execute([$trabajadorId]);
    return $stmt->fetchAll();
}
