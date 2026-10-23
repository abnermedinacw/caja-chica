<?php
require_once __DIR__ . '/../../config/database.php';

function listarFondosDesembolsoBbva(int $empresaId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT
            f.id,
            f.empresa_id,
            f.tipo,
            f.monto_asignado AS importe,
            f.estado,
            f.fecha_asignacion,
            f.fecha_desembolso,
            t.nombres AS trabajador,
            t.dni,
            t.cuenta_bbva,
            EXISTS (
                SELECT 1
                FROM caja_chica cc
                WHERE cc.fondo_id = f.id
            ) AS tiene_cajas
        FROM fondo f
        INNER JOIN trabajador t ON t.id = f.trabajador_id
        WHERE f.empresa_id = ?
            AND (
                f.estado = 'PENDIENTE_DESEMBOLSO'
                OR (
                    f.estado = 'ACTIVO'
                    AND f.fecha_desembolso IS NOT NULL
                )
            )
        ORDER BY
            f.estado,
            f.fecha_asignacion,
            t.nombres"
    );
    $stmt->execute([$empresaId]);
    return $stmt->fetchAll();
}

function bloquearFondosDesembolsoBbva(array $fondosIds): array
{
    $pdo = obtenerConexion();
    $fondosIds = array_values(
        array_unique(
            array_filter(
                array_map('intval', $fondosIds),
                fn(int $id): bool => $id > 0
            )
        )
    );

    if ($fondosIds === []) return [];

    $marcadores = implode(',', array_fill(0, count($fondosIds), '?'));

    $stmt = $pdo->prepare(
        "SELECT
            f.id,
            f.empresa_id,
            f.monto_asignado AS importe,
            f.estado,
            f.fecha_desembolso,
            t.nombres AS trabajador,
            t.dni,
            t.cuenta_bbva,
            EXISTS (
                SELECT 1
                FROM caja_chica cc
                WHERE cc.fondo_id = f.id
            ) AS tiene_cajas
        FROM fondo f
        INNER JOIN trabajador t ON t.id = f.trabajador_id
        WHERE f.id IN ($marcadores)
        FOR UPDATE"
    );

    $stmt->execute($fondosIds);
    return $stmt->fetchAll();
}

function marcarFondosDesembolsados(array $fondosIds): void
{
    $pdo = obtenerConexion();
    $marcadores = implode(',', array_fill(0, count($fondosIds), '?'));

    $stmt = $pdo->prepare(
        "UPDATE fondo
        SET
            estado = 'ACTIVO',
            fecha_desembolso = NOW()
        WHERE id IN ($marcadores)
            AND estado = 'PENDIENTE_DESEMBOLSO'"
    );

    $stmt->execute($fondosIds);

    if($stmt->rowCount() !== count($fondosIds)) {
        throw new DomainException('Uno o más fondos ya fueron desembolsados o cambiaron de estado.');
    }
}

function reactivarFondosDesembolsoBbva(array $fondosIds): void
{
    $pdo = obtenerConexion();
    $marcadores = implode(',', array_fill(0, count($fondosIds), '?'));

    $stmt = $pdo->prepare(
        "UPDATE fondo f
        SET
            f.estado = 'PENDIENTE_DESEMBOLSO',
            f.fecha_desembolso = NULL
        WHERE f.id IN ($marcadores)
            AND f.estado = 'ACTIVO'
            AND f.fecha_desembolso IS NOT NULL
            AND NOT EXISTS (
                SELECT 1
                FROM caja_chica cc
                WHERE cc.fondo_id = f.id
            )"
    );
    
    $stmt->execute($fondosIds);
    if ($stmt->rowCount() !== count($fondosIds)) {
        throw new DomainException('Solo se pueden reactivar fondos que todavía no tengan resúmenes asociados.');
    }
}
