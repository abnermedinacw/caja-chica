<?php
require_once __DIR__ . '/../../config/database.php';

function obtenerCargoDeTrabajador(int $trabajadorId): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT
            c.id,
            c.nombre,
            c.cargo_padre_id
        FROM trabajador t
        JOIN cargo c ON c.id = t.cargo_id
        WHERE t.id = ?"
    );
    $stmt->execute([$trabajadorId]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function buscarJefeArea(int $areaId): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT 
            u.id AS usuario_id,
            u.rol,
            t.id AS trabajador_id, 
            t.nombres
        FROM trabajador t
        JOIN usuario u ON u.trabajador_id = t.id
        WHERE t.area_id = ?
            AND t.es_jefe = 1
            AND u.estado = 'ACTIVO'
        LIMIT 1"
    );

    $stmt->execute([$areaId]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

// Función que se usará para crear cargos que vienen de la bbdd de tsoluciona
// Busca por nombre, si no existe, se crea  y devuelve su id
function obtenerOcrearCargoPorNombre(string $nombre): int
{
    $pdo = obtenerConexion();
    $nombre = trim($nombre);

    $stmt = $pdo->prepare("SELECT id FROM cargo WHERE nombre = ?");
    $stmt->execute([$nombre]);
    $fila = $stmt->fetch();
    
    if ($fila) return (int)$fila['id'];

    $stmt = $pdo->prepare("INSERT INTO cargo (nombre) VALUES (?)");
    $stmt->execute([$nombre]);
    return (int)$pdo->lastInsertId();
}
