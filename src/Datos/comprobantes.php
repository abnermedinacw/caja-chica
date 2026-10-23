<?php

require_once __DIR__ . '/../../config/database.php';

function listarTiposComprobante(): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->query("SELECT id, nombre FROM tipo_comprobante ORDER BY id");
    return $stmt->fetchAll();
}

function crearComprobante(
    int $tipoComprobanteId, 
    string $serie, 
    string $numero, 
    string $fecha, 
    float $monto,
    ?string $proveedorRuc,
    ?string $proveedorRazonSocial,
    ?string $proveedorCondicion,
    ?string $proveedorEstado,
    ?bool $proveedorValido
): int 
{
    $pdo = obtenerConexion();

    $stmt = $pdo->prepare(
        "SELECT id
        FROM comprobante
        WHERE tipo_comprobante_id = ? AND serie = ? AND numero = ?"
    );

    $stmt->execute([$tipoComprobanteId, $serie, $numero]);
    $existente = $stmt->fetch();

    if ($existente){
        return (int)$existente['id'];
    }

    $stmt = $pdo->prepare(
        "INSERT INTO comprobante (tipo_comprobante_id, serie, numero, fecha, monto, proveedor_ruc, proveedor_razon_social, proveedor_condicion_sunat, proveedor_estado_sunat, proveedor_valido, fecha_verificacion_proveedor)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->execute([
        $tipoComprobanteId, $serie, $numero, $fecha, $monto,
        $proveedorRuc, $proveedorRazonSocial, $proveedorCondicion, $proveedorEstado,
        $proveedorValido === null ? null : ($proveedorValido ? 1 : 0),
        $proveedorRuc !== null ? date('Y-m-d H:i:s') : null,
    ]);
    return(int)$pdo->lastInsertId();
}

function obtenerNombreTipoComprobante(int $id): ?string
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT nombre FROM tipo_comprobante WHERE id = ?");
    $stmt->execute([$id]);
    $fila = $stmt->fetch();
    return $fila ? $fila['nombre'] : null;
}