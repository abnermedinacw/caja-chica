<?php
require_once __DIR__ . '/../../config/database.php';

function listarEmpresasPagosBbva(): array
{
    $pdo = obtenerConexion();

    return $pdo->query(
        "SELECT
            id,
            ruc,
            razon_social,
            cuenta_cargo_bbva,
            cuenta_cargo_bbva_fecha_actualizacion
        FROM empresa
        ORDER BY razon_social"
    )->fetchAll();
}

function obtenerEmpresaPagoBbva(int $empresaId, bool $bloquear = false): ?array
{
    $pdo = obtenerConexion();
    $sql = 
        "SELECT
            id,
            ruc,
            razon_social,
            cuenta_cargo_bbva,
            cuenta_cargo_bbva_fecha_actualizacion
        FROM empresa
        WHERE id = ?";

    if ($bloquear) $sql .= " FOR UPDATE";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empresaId]);

    $empresa = $stmt->fetch();
    return $empresa ?: null;
}

function actualizarCuentaCargoBbva(int $empresaId, string $cuenta): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "UPDATE empresa
        SET
            cuenta_cargo_bbva = ?,
            cuenta_cargo_bbva_fecha_actualizacion = NOW()
        WHERE id = ?"
    );

    $stmt->execute([$cuenta, $empresaId]);
}

function listarCajasPagosBbva(int $empresaId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT
            cc.id,
            cc.numero,
            cc.fecha_emision,
            COALESCE(cc.monto_rendicion, cc.total_egresos) AS importe,
            cc.txt_bbva_descargado,
            cc.txt_bbva_fecha_descarga,
            t.id AS trabajador_id,
            t.nombres AS trabajador,
            t.dni,
            t.cuenta_bbva
        FROM caja_chica cc
        INNER JOIN trabajador t ON t.id = cc.trabajador_id
        WHERE cc.empresa_id = ? AND cc.estado_general = 'VISADO'
        ORDER BY cc.txt_bbva_descargado, cc.fecha_emision, cc.id"
    );

    $stmt->execute([$empresaId]);
    return $stmt->fetchAll();
}

function bloquearCajasPagosBbva(array $cajasIds): array
{
    $pdo = obtenerConexion();

    $cajasIds = array_values(array_unique(array_map('intval', $cajasIds)));
    if ($cajasIds === []) return [];
    $marcadores = implode(',', array_fill(0, count($cajasIds), '?'));

    $stmt = $pdo->prepare(
        "SELECT
            cc.id,
            cc.numero,
            cc.empresa_id,
            cc.estado_general,
            cc.txt_bbva_descargado,
            COALESCE(cc.monto_rendicion, cc.total_egresos) AS importe,
            t.id AS trabajador_id,
            t.nombres AS trabajador,
            t.dni,
            t.cuenta_bbva
        FROM caja_chica cc
        INNER JOIN trabajador t ON t.id = cc.trabajador_id
        WHERE cc.id IN ($marcadores)
        ORDER BY cc.numero
        FOR UPDATE"
    );

    $stmt->execute($cajasIds);
    return $stmt->fetchAll();
}

// Marcar cajas con txt descargado
function marcarTxtDescargado(array $cajasIds): void
{
    $pdo = obtenerConexion();
    $cajasIds = array_values(array_unique(array_map('intval', $cajasIds)));
    $marcadores = implode(',', array_fill(0, count($cajasIds), '?'));

    $stmt = $pdo->prepare(
        "UPDATE caja_chica
        SET
            txt_bbva_descargado = 1,
            txt_bbva_fecha_descarga = NOW()
        WHERE id IN ($marcadores)
            AND txt_bbva_descargado = 0"
    );

    $stmt->execute($cajasIds);

    if ($stmt->rowCount() !== count($cajasIds)) {
        throw new DomainException('Una o más cajas ya fueron incluidas en otra descarga.');
    }
}

function reactivarTxtBbva(array $cajasIds): void
{
    $pdo = obtenerConexion();
    $cajasIds = array_values(array_unique(array_map('intval', $cajasIds)));
    $marcadores = implode(',',array_fill(0, count($cajasIds), '?'));

    $stmt = $pdo->prepare(
        "UPDATE caja_chica
        SET
            txt_bbva_descargado = 0,
            txt_bbva_fecha_descarga = NULL
        WHERE id IN ($marcadores) AND txt_bbva_descargado = 1"
    );

    $stmt->execute($cajasIds);

    if ($stmt->rowCount() !== count($cajasIds)) {
        throw new DomainException('Una o más cajas ya estaban habilitadas.');
    }
}
