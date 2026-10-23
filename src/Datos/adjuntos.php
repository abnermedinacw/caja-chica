<?php
require_once __DIR__ . '/../../config/database.php';

// --- Documento Contable ---
function insertarAdjuntoDocumentoContable(
    int $documentoId,
    ?int $detalleId,
    string $nombreArchivo,
    string $ruta,
    int $subidoPor,
    string $tipoArchivo = 'EVIDENCIA'
): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO adjunto_documento_contable
            (documento_contable_id, detalle_id, tipo_archivo, nombre_archivo, ruta, subido_por)
        VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$documentoId, $detalleId, $tipoArchivo, $nombreArchivo, $ruta, $subidoPor]);
}

function obtenerAdjuntoDocumentoContablePorId(int $adjuntoId, int $documentoId): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT id, nombre_archivo, ruta FROM adjunto_documento_contable WHERE id = ? AND documento_contable_id = ?"
    );
    $stmt->execute([$adjuntoId, $documentoId]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function eliminarAdjuntoDocumentoContablePorId(int $adjuntoId): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("DELETE FROM adjunto_documento_contable WHERE id = ?");
    $stmt->execute([$adjuntoId]);
}

function obtenerAdjuntoDocumentoContablePorDetalleYTipo(int $documentoId, int $detalleId, string $tipoArchivo): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT id, nombre_archivo, ruta
         FROM adjunto_documento_contable
         WHERE documento_contable_id = ? AND detalle_id = ? AND tipo_archivo = ?"
    );
    $stmt->execute([$documentoId, $detalleId, $tipoArchivo]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

/** Solo para el generador del PDF: nunca se expone ruta al navegador. */
function obtenerEvidenciasContablesParaPdf(int $documentoId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT a.nombre_archivo, a.ruta, d.item
         FROM adjunto_documento_contable a
         INNER JOIN documento_contable_detalle d ON d.id = a.detalle_id
         WHERE a.documento_contable_id = ? AND a.tipo_archivo = 'EVIDENCIA'
         ORDER BY d.item, a.fecha_subida"
    );
    $stmt->execute([$documentoId]);
    return $stmt->fetchAll();
}

function eliminarAdjuntosDocumentoContable(int $documentoId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT ruta FROM adjunto_documento_contable WHERE documento_contable_id = ?");
    $stmt->execute([$documentoId]);
    $rutas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("DELETE FROM adjunto_documento_contable WHERE documento_contable_id = ?");
    $stmt->execute([$documentoId]);
    return $rutas;
}

// --- Documento No Contable ---
function insertarAdjuntoDocumentoNoContable(
    int $documentoId,
    ?int $detalleId,
    string $nombreArchivo,
    string $ruta,
    int $subidoPor,
    string $tipoArchivo = 'EVIDENCIA'
): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO adjunto_documento_no_contable
            (documento_no_contable_id, detalle_id, tipo_archivo, nombre_archivo, ruta, subido_por)
        VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$documentoId, $detalleId, $tipoArchivo, $nombreArchivo, $ruta, $subidoPor]);
}

function obtenerAdjuntoDocumentoNoContablePorId(int $adjuntoId, int $documentoId): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT id, nombre_archivo, ruta FROM adjunto_documento_no_contable WHERE id = ? AND documento_no_contable_id = ?"
    );
    $stmt->execute([$adjuntoId, $documentoId]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function eliminarAdjuntoDocumentoNoContablePorId(int $adjuntoId): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "DELETE FROM adjunto_documento_no_contable WHERE id = ?"
    );
    $stmt->execute([$adjuntoId]);
}

function obtenerAdjuntoDocumentoNoContablePorDetalleYTipo(int $documentoId, int $detalleId, string $tipoArchivo): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT id, nombre_archivo, ruta
         FROM adjunto_documento_no_contable
         WHERE documento_no_contable_id = ? AND detalle_id = ? AND tipo_archivo = ?"
    );
    $stmt->execute([$documentoId, $detalleId, $tipoArchivo]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function obtenerEvidenciasNoContablesParaPdf(int $documentoId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT a.nombre_archivo, a.ruta, d.item
         FROM adjunto_documento_no_contable a
         INNER JOIN documento_no_contable_detalle d ON d.id = a.detalle_id
         WHERE a.documento_no_contable_id = ? AND a.tipo_archivo = 'EVIDENCIA'
         ORDER BY d.item, a.fecha_subida"
    );
    $stmt->execute([$documentoId]);
    return $stmt->fetchAll();
}

function eliminarAdjuntosDocumentoNoContable(int $documentoId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT ruta FROM adjunto_documento_no_contable WHERE documento_no_contable_id = ?");
    $stmt->execute([$documentoId]);
    $rutas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("DELETE FROM adjunto_documento_no_contable WHERE documento_no_contable_id = ?");
    $stmt->execute([$documentoId]);
    return $rutas;
}

// --- Planilla de Movilidad ---
function insertarAdjuntoMovilidad(
    int $planillaId,
    ?int $detalleId,
    string $nombreArchivo,
    string $ruta,
    int $subidoPor,
    string $tipoArchivo = 'EVIDENCIA'
): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO adjunto_movilidad
            (planilla_id, detalle_id, tipo_archivo, nombre_archivo, ruta, subido_por)
        VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$planillaId, $detalleId, $tipoArchivo, $nombreArchivo, $ruta, $subidoPor]);
}

function obtenerAdjuntoMovilidadPorId(int $adjuntoId, int $planillaId): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT id, nombre_archivo, ruta FROM adjunto_movilidad WHERE id = ? AND planilla_id = ?"
    );
    $stmt->execute([$adjuntoId, $planillaId]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function obtenerAdjuntoMovilidadPorDetalleYTipo(int $planillaId, int $detalleId, string $tipoArchivo): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT id, nombre_archivo, ruta
         FROM adjunto_movilidad
         WHERE planilla_id = ? AND detalle_id = ? AND tipo_archivo = ?"
    );
    $stmt->execute([$planillaId, $detalleId, $tipoArchivo]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function obtenerEvidenciasMovilidadParaPdf(int $planillaId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT a.nombre_archivo, a.ruta, d.item
         FROM adjunto_movilidad a
         INNER JOIN planilla_movilidad_detalle d ON d.id = a.detalle_id
         WHERE a.planilla_id = ? AND a.tipo_archivo = 'EVIDENCIA'
         ORDER BY d.item, a.fecha_subida"
    );
    $stmt->execute([$planillaId]);
    return $stmt->fetchAll();
}

function eliminarAdjuntoMovilidadPorId(int $adjuntoId): void 
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("DELETE FROM adjunto_movilidad WHERE id = ?");
    $stmt->execute([$adjuntoId]);
}

// Resumen (adjuntos generales, no atados a un item puntual) 
function insertarAdjuntoResumen(int $resumenId, string $nombreArchivo, string $ruta, int $subidoPor): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO adjunto_caja_chica (caja_chica_id, nombre_archivo, ruta, subido_por)
        VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$resumenId, $nombreArchivo, $ruta, $subidoPor]);
}

function obtenerAdjuntoResumenPorId(int $adjuntoId, int $resumenId): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT id, nombre_archivo, ruta FROM adjunto_caja_chica WHERE id = ? AND caja_chica_id = ?"
    );
    $stmt->execute([$adjuntoId, $resumenId]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}
