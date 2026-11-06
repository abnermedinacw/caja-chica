<?php
require_once __DIR__ . '/../../config/database.php';

function obtenerNiveles(): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->query("SELECT id, codigo, nombre, orden, rol_requerido, firma_visible FROM nivel_aprobacion ORDER BY orden");
    return $stmt->fetchAll();
}

function obtenerNivelPorOrden(int $orden): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT id, codigo, nombre, orden, rol_requerido FROM nivel_aprobacion WHERE orden = ?");
    $stmt->execute([$orden]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function obtenerNivelPorId(int $id): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT id, codigo, nombre, orden, rol_requerido FROM nivel_aprobacion WHERE id = ?");
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
        WHERE na.rol_requerido <> 'JEFATURA'
        AND NOT EXISTS (
            SELECT 1 FROM usuario u
            WHERE u.rol = na.rol_requerido
            AND u.estado = 'ACTIVO'
        )
        ORDER BY na.orden"
    );
    return array_column($stmt->fetchAll(), 'codigo');
}

function obtenerEncargadosNivel(int $nivelId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT u.id, u.username, u.correo, u.rol
        FROM usuario u
        JOIN nivel_aprobacion na ON na.rol_requerido = u.rol
        WHERE na.id = ?
        AND u.estado = 'ACTIVO'
        ORDER BY u.id"
    );
    $stmt->execute([$nivelId]);
    return $stmt->fetchAll();
}