<?php
require_once __DIR__ . '/../../config/database.php';

function listarEmpresas(): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->query("SELECT id, razon_social, ruc FROM empresa ORDER BY razon_social");
    return $stmt->fetchAll();
}

function obtenerEmpresaPorId(int $id): ?array 
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT id, razon_social, ruc FROM empresa WHERE id = ?");
    $stmt->execute([$id]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function obtenerEmpresaPorRuc(string $ruc): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT id, ruc, razon_social FROM empresa WHERE ruc = ?");
    $stmt->execute([$ruc]);
    return $stmt->fetch();
}
