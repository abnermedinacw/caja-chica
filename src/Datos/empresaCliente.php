<?php
require_once __DIR__ . '/../../config/database.php';

function obtenerEmpresasClientePorRuc(string $ruc): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT id, ruc_cli, razon_social_cli FROM empresa_cliente WHERE ruc_cli = ?"
    );

    $stmt->execute([$ruc]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function crearEmpresaCliente(string $ruc, string $razonSocialCli): int
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO empresa_cliente (ruc_cli, razon_social_cli) VALUES (?,?)"
    );
    $stmt->execute([$ruc, $razonSocialCli]);
    return (int)$pdo->lastInsertId();
}