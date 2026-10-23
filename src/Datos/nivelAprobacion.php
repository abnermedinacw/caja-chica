<?php
require_once __DIR__ . '/../../config/database.php';

function obtenerNiveles(): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->query("SELECT id, codigo, nombre, orden, aprobador_usuario_id, rol_requerido, firma_visible FROM nivel_aprobacion ORDER BY orden");
    return $stmt->fetchAll();
}

function obtenerNivelPorOrden(int $orden): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT id, codigo, nombre, orden, aprobador_usuario_id, rol_requerido FROM nivel_aprobacion WHERE orden = ?");
    $stmt->execute([$orden]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function obtenerNivelPorId(int $id): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT id, codigo, nombre, orden, aprobador_usuario_id, rol_requerido FROM nivel_aprobacion WHERE id = ?");
    $stmt->execute([$id]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function obtenerPrimerNivel(): ?array
{
    return obtenerNivelPorOrden(1);
}

function obtenerNivelesSinResponsable(): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->query(
        "SELECT na.codigo
        FROM nivel_aprobacion na
        LEFT JOIN usuario u ON u.id = na.aprobador_usuario_id
        WHERE na.rol_requerido <> 'JEFATURA'
        AND (
            na.aprobador_usuario_id IS NULL
            OR u.id IS NULL
            OR u.estado <> 'ACTIVO'
            OR u.rol <> na.rol_requerido
        )
        ORDER BY na.orden"
    );
    return array_column($stmt->fetchAll(), 'codigo');
}