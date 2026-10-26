<?php

require_once __DIR__ . '/../../config/database.php';

function crearFondo(int $empresaId, int $trabajadorId, string $tipo, float $montoAsignado, int $asignadoPor, string $fechaAsignacion): int
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO fondo (empresa_id, trabajador_id, tipo, monto_asignado, asignado_por, fecha_asignacion, estado)
        VALUES (?,?,?,?,?,?,'PENDIENTE_DESEMBOLSO')"
    );

    $stmt->execute([$empresaId, $trabajadorId, $tipo, $montoAsignado, $asignadoPor, $fechaAsignacion]);
    return (int)$pdo->lastInsertId();
}

function obtenerFondoPorId(int $fondoId): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT
            f.*,
            IFNULL((
                SELECT SUM(cc.total_egresos)
                FROM caja_chica cc
                WHERE cc.fondo_id = f.id
                  AND (
                    cc.estado_general = 'EN_REVISION'
                    OR (cc.estado_general = 'RECHAZADO' AND cc.nivel_actual_id IS NOT NULL)
                    OR (cc.estado_general = 'VISADO' AND cc.txt_bbva_descargado = 0)
                  )
            ), 0) AS monto_comprometido
        FROM fondo f
        WHERE f.id = ?"
    );
    $stmt->execute([$fondoId]);
    $fondo = $stmt->fetch();
    return $fondo ?: null;
}

function actualizarMontoAsignadoFondo(int $fondoId, float $monto): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare('UPDATE fondo SET monto_asignado = ? WHERE id = ?');
    $stmt->execute([$monto, $fondoId]);
}

// Lista todos los fondos para la vista de tesorería y gerencia
function listarFondos(): array
{
    $pdo = obtenerConexion();
    $sql = 
        "SELECT
            f.id AS fondo_id,
            f.empresa_id,
            e.razon_social AS empresa,
            f.trabajador_id,
            t.nombres AS trabajador,
            t.dni,
            f.tipo,
            f.estado,
            f.monto_asignado,
            f.fecha_asignacion,
            f.monto_asignado - IFNULL((
                SELECT SUM(cc.total_egresos)
                FROM caja_chica cc
                WHERE cc.fondo_id = f.id
                    AND (
                        cc.estado_general = 'EN_REVISION'
                        OR (cc.estado_general = 'RECHAZADO' AND cc.nivel_actual_id IS NOT NULL)
                        OR (cc.estado_general = 'VISADO' AND cc.txt_bbva_descargado = 0)
                    )
                ),0) AS saldo_disponible
        FROM fondo f
        JOIN empresa e ON e.id = f.empresa_id
        JOIN trabajador t ON t.id = f.trabajador_id
        ORDER BY f.estado, f.fecha_asignacion DESC, f.id DESC";

    return $pdo->query($sql)->fetchAll();
}

function tieneFondoRecurrenteActivo(int $empresaId, int $trabajadorId): bool
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT 1
        FROM fondo
        WHERE empresa_id = ?
            AND trabajador_id = ?
            AND tipo = 'RECURRENTE'
            AND estado IN ('PENDIENTE_DESEMBOLSO', 'ACTIVO')
        LIMIT 1"
    );
    $stmt->execute([$empresaId, $trabajadorId]);
    return (bool) $stmt->fetchColumn();
}

// Fondos activos de un trabajaador con su saldo disponible
function obtenerFondosActivos(int $trabajadorId): array
{
    $pdo = obtenerConexion();
    $sql = 
        "SELECT
            f.id AS fondo_id,
            f.empresa_id,
            e.razon_social AS empresa,
            f.tipo,
            f.monto_asignado,
            f.monto_asignado - IFNULL((
                SELECT SUM(cc.total_egresos)
                FROM caja_chica cc
                WHERE cc.fondo_id = f.id 
                    AND (
                        cc.estado_general = 'EN_REVISION'
                        OR (cc.estado_general = 'RECHAZADO' AND cc.nivel_actual_id IS NOT NULL)
                        OR (cc.estado_general = 'VISADO' AND cc.txt_bbva_descargado = 0)
                    )
                ), 0) AS saldo_disponible
        FROM fondo f
        JOIN empresa e ON e.id = f.empresa_id
        WHERE f.trabajador_id = ? AND f.estado = 'ACTIVO'
        ORDER BY e.razon_social, f.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$trabajadorId]);
        return $stmt->fetchAll();
}

// Todos los fondos de un trabajador, hasta los cerrados, para ver histórico
function obtenerFondosTrabajador(int $trabajadorId): array
{
    $pdo = obtenerConexion();
    $sql =
        "SELECT
            f.id AS fondo_id,
            f.empresa_id,
            e.razon_social AS empresa,
            f.tipo,
            f.estado,
            f.monto_asignado,
            f.fecha_asignacion,
            f.monto_asignado - IFNULL((
                SELECT SUM(cc.total_egresos)
                FROM caja_chica cc
                WHERE cc.fondo_id = f.id
                    AND (
                        cc.estado_general = 'EN_REVISION'
                        OR (cc.estado_general = 'RECHAZADO' AND cc.nivel_actual_id IS NOT NULL)
                        OR (cc.estado_general = 'VISADO' AND cc.txt_bbva_descargado = 0)
                    )
                ), 0) AS saldo_disponible
        FROM fondo f
        JOIN empresa e ON e.id = f.empresa_id
        WHERE f.trabajador_id = ?
        ORDER BY f.estado, f.fecha_asignacion DESC, f.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$trabajadorId]);
    return $stmt->fetchAll();
}

// Se encarga de validar si el fondo es activo y pertenece al trabajador. 
// Retorna los datos del fondo si es válido, o null si no lo es.
// Se hace aparte porque se necesita validar el fondo en la función guardarCajaChica, 
// y no se necesita toda la información de los fondos activos, solo si el fondo es válido o no.
function validarFondoActivo(int $fondoId, int $trabajadorId): ?array 
{
    $pdo = obtenerConexion();
    $sql = 
    "SELECT 
	    id, 
	    empresa_id, 
        tipo,
	    monto_asignado
    FROM fondo
    WHERE id = ? AND trabajador_id = ? AND estado = 'ACTIVO'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fondoId, $trabajadorId]);
    $fondo = $stmt->fetch();
    return $fondo ?: null;
}

function obtenerSaldoDisponibleFondo(int $fondoId): float 
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT f.monto_asignado - IFNULL((
            SELECT SUM(cc.total_egresos)
            FROM caja_chica cc
            WHERE cc.fondo_id = f.id
                AND (
                    cc.estado_general = 'EN_REVISION'
                    OR (cc.estado_general = 'RECHAZADO' AND cc.nivel_actual_id IS NOT NULL)                
                    OR (cc.estado_general = 'VISADO' AND cc.txt_bbva_descargado = 0)
                )
            ), 0) AS saldo
        FROM fondo f WHERE f.id = ?"
    );
    $stmt->execute([$fondoId]);
    $fila = $stmt->fetch();
    return $fila ? (float)$fila['saldo'] : 0.0;
}
