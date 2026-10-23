<?php
require_once __DIR__ . '/../../config/database.php';

function listarTrabajadores(): array
{
    $pdo = obtenerConexion();
    $sql = 
        "SELECT 
            t.id,
            t.nombres,
            t.dni,
            e.razon_social AS empresa,
            c.nombre AS cargo,
            a.nombre AS area,
            t.es_jefe,
            EXISTS (
                SELECT 1
                FROM usuario u
                WHERE u.trabajador_id = t.id
            ) AS tiene_usuario
        FROM trabajador t
        JOIN empresa e ON e.id = t.empresa_id
        JOIN area a ON a.id = t.area_id
        LEFT JOIN cargo c ON c.id = t.cargo_id
        ORDER BY t.nombres";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function obtenerTrabajadorPorId(int $id): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT id, nombres FROM trabajador WHERE id = ?");
    $stmt->execute([$id]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function obtenerTrabajadorPorDni(string $dni): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT id, nombres FROM trabajador WHERE dni = ?");
    $stmt->execute([$dni]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

// IDs de los trabajadores que reportan a un cargo+área específicos — mismo
// criterio que ya usa buscarJefeArea() para encontrar AL jefe (cargo_padre_id
// + area_id), pero acá en la dirección contraria: dado el cargo del jefe,
// busca a quiénes tiene a cargo.
function listarIdsTrabajadoresACargoDe(int $cargoJefeId, int $areaId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT t.id
        FROM trabajador t
        JOIN cargo c ON c.id = t.cargo_id
        WHERE c.cargo_padre_id = ? AND t.area_id = ?"
    );
    $stmt->execute([$cargoJefeId, $areaId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

function listarIdsTrabajadoresDeArea(int $areaId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT id FROM trabajador WHERE area_id = ?");
    $stmt->execute([$areaId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

function crearTrabajador(
    int $empresaId, 
    ?int $empresaClienteId, 
    int $areaId, 
    ?int $cargoId, 
    string $dni, 
    string $nombres,
    ?string $cuentaBbva,
    bool $esJefe
): int
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO trabajador (empresa_id, empresa_cliente_id, area_id, cargo_id, dni, nombres, cuenta_bbva, cuenta_bbva_fecha_sincronizacion, es_jefe)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)"
    );

    $stmt->execute([$empresaId, $empresaClienteId, $areaId, $cargoId, $dni, $nombres, $cuentaBbva, $esJefe ? 1 : 0]);
    return (int)$pdo->lastInsertId();
}

function actualizarCuentaBbvaTrabajador(int $trabajadorId, string $cuentaBbva): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "UPDATE trabajador
        SET cuenta_bbva = ?
        WHERE id = ?"
    );
    
    $stmt->execute([$cuentaBbva, $trabajadorId]);
}