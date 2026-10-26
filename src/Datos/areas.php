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

function listarAreas(): array
{
    return obtenerConexion()->query('SELECT id, empresa_id, nombre FROM area ORDER BY nombre')->fetchAll();
}

function obtenerAreaPorIdEmpresa(int $areaId, int $empresaId): ?array
{
    $stmt = obtenerConexion()->prepare('SELECT id, nombre FROM area WHERE id = ? AND empresa_id = ?');
    $stmt->execute([$areaId, $empresaId]);
    return $stmt->fetch() ?: null;
}
