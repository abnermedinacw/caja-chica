<?php
// SQL para usuarios
require_once __DIR__ . '/../../config/database.php';

function buscarUsuarioPorUsername(string $username): ?array
{
    $pdo = obtenerConexion();
    $sql = 
        "SELECT 
            u.id, 
            u.username, 
            u.correo,
            u.password, 
            u.rol, 
            u.es_admin,
            u.estado,
            u.trabajador_id,
            t.nombres AS trabajador_nombre, 
            t.empresa_id, 
            t.area_id,
            t.es_jefe,
            a.nombre AS area_nombre,
            c.nombre AS cargo_nombre
        FROM usuario u
        LEFT JOIN trabajador t ON t.id = u.trabajador_id
        LEFT JOIN area a ON a.id = t.area_id
        LEFT JOIN cargo c ON c.id = t.cargo_id
        WHERE u.username = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $usuario = $stmt->fetch();
        return $usuario ?: null;
}

function crearUsuario(
    int $trabajadorId,
    string $username,
    string $correo,
    string $password,
    string $rol,
    bool $esAdmin,
    int $creador): int
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO usuario (trabajador_id, username, correo, password, rol, es_admin, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->execute([$trabajadorId, $username, $correo, $password, $rol, $esAdmin ? 1 : 0, $creador]);
    return (int)$pdo->lastInsertId();
}

function obtenerPerfilUsuario(int $usuarioId): ?array
{
    $pdo = obtenerConexion();

    $stmt = $pdo->prepare(
        "SELECT 
            u.id AS usuario_id,
            u.username,
            u.correo,
            u.rol,
            u.estado,
            u.es_admin,
            u.fecha_creacion,

            t.id AS trabajador_id,
            t.dni,
            t.nombres,
            t.es_jefe,
            t.cuenta_bbva,

            e.razon_social AS empresa,
            a.nombre AS area,
            c.nombre AS cargo,

            fd.ruta_imagen AS firma_ruta,
            fd.fecha_registro AS firma_fecha_registro,
            fd.fecha_actualizacion AS firma_fecha_actualizacion
        FROM usuario u
        LEFT JOIN trabajador t ON t.id = u.trabajador_id
        LEFT JOIN empresa e ON e.id = t.empresa_id
        LEFT JOIN area a ON a.id = t.area_id
        LEFT JOIN cargo c ON c.id = t.cargo_id
        LEFT JOIN firma_digital fd ON fd.id = (
            SELECT MAX(fdd.id)
            FROM firma_digital fdd
            WHERE fdd.tipo = 'PERSONAL'
                AND fdd.usuario_id = u.id
        )
        WHERE u.id = ?"
    );

    $stmt->execute([$usuarioId]);
    return $stmt->fetch() ?: null;
}

function buscarUsuarioPorCorreo(string $correo): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT id
        FROM usuario
        WHERE correo = ?"
    );

    $stmt->execute([$correo]);
    return $stmt->fetch() ?: null;
}

function actualizarCorreoUsuario(int $usuarioId, string $correo): void
{
    $stmt = obtenerConexion()->prepare('UPDATE usuario SET correo = ? WHERE id = ?');
    $stmt->execute([$correo, $usuarioId]);
}

function obtenerUsuarioNotificacion(int $usuarioId): ?array
{
    $pdo = obtenerConexion();

    $stmt = $pdo->prepare(
        "SELECT
            u.id,
            u.correo,
            t.nombres
        FROM usuario u
        INNER JOIN trabajador t ON t.id = u.trabajador_id
        WHERE u.id = ? AND u.estado = 'ACTIVO'"
    );

    $stmt->execute([$usuarioId]);
    return $stmt->fetch() ?:null;
}
