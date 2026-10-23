<?php
require_once __DIR__ . '/../../config/database.php';

function obtenerOcrearAreaPorNombre(int $empresaId, string $nombre): int 
{
    $pdo = obtenerConexion();
    $nombre = trim($nombre);

    $stmt = $pdo->prepare("SELECT id FROM area WHERE empresa_id = ? AND nombre = ?");
    $stmt->execute([$empresaId, $nombre]);
    $fila = $stmt->fetch();

    if ($fila) return (int)$fila['id'];

    $stmt = $pdo->prepare("INSERT INTO area (empresa_id, nombre) VALUES (?, ?)");
    $stmt->execute([$empresaId, $nombre]);
    return (int)$pdo->lastInsertId();
}

function existeJefe(int $areaId): bool
{
    $pdo = obtenerConexion();

    $stmt = $pdo->prepare(
        "SELECT 1
        FROM trabajador
        WHERE area_id = ? AND es_jefe = 1
        LIMIT 1"
    );

    $stmt->execute([$areaId]);
    return (bool)$stmt->fetchColumn();
}