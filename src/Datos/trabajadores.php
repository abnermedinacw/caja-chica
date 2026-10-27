<?php
require_once __DIR__ . '/../../config/database.php';

function filtrosTrabajadores(string $busqueda, int $empresaId): array
{
    $condiciones = [];
    $parametros = [];
    if ($busqueda !== '') {
        $texto = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busqueda) . '%';
        $condiciones[] = "(t.nombres LIKE ? ESCAPE '!' OR t.dni LIKE ? ESCAPE '!')";
        $parametros[] = $texto;
        $parametros[] = $texto;
    }
    if ($empresaId > 0) {
        $condiciones[] = 't.empresa_id = ?';
        $parametros[] = $empresaId;
    }
    return [$condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '', $parametros];
}

function contarTrabajadores(string $busqueda = '', int $empresaId = 0): int
{
    [$where, $parametros] = filtrosTrabajadores($busqueda, $empresaId);
    $stmt = obtenerConexion()->prepare(
        'SELECT COUNT(*) FROM trabajador t
         JOIN empresa e ON e.id = t.empresa_id
         JOIN area a ON a.id = t.area_id' . $where
    );
    $stmt->execute($parametros);
    return (int)$stmt->fetchColumn();
}

function listarTrabajadores(string $busqueda = '', int $empresaId = 0, ?int $limite = null, int $desplazamiento = 0): array
{
    $pdo = obtenerConexion();
    [$where, $parametros] = filtrosTrabajadores($busqueda, $empresaId);
    $sql = 
        "SELECT 
            t.id,
            t.nombres,
            t.dni,
            e.razon_social AS empresa,
            c.nombre AS cargo,
            a.nombre AS area,
            jefe.nombres AS jefe,
            CASE
                WHEN EXISTS (
                    SELECT 1 FROM usuario u
                    WHERE u.trabajador_id = t.id AND u.estado = 'ACTIVO'
                ) THEN 'ACTIVO'
                WHEN EXISTS (
                    SELECT 1 FROM usuario u WHERE u.trabajador_id = t.id
                ) THEN 'INACTIVO'
                ELSE 'SIN_USUARIO'
            END AS acceso
        FROM trabajador t
        JOIN empresa e ON e.id = t.empresa_id
        JOIN area a ON a.id = t.area_id
        LEFT JOIN cargo c ON c.id = t.cargo_id
        LEFT JOIN usuario uj ON uj.id = t.jefe_usuario_id
        LEFT JOIN trabajador jefe ON jefe.id = uj.trabajador_id" . $where . "
        ORDER BY t.id DESC";
    if ($limite !== null) {
        $sql .= ' LIMIT ? OFFSET ?';
        $parametros[] = max(1, min(100, $limite));
        $parametros[] = max(0, $desplazamiento);
    }
    $stmt = $pdo->prepare($sql);
    foreach ($parametros as $indice => $valor) {
        $stmt->bindValue($indice + 1, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function obtenerTrabajadorPorId(int $id): ?array
{
    $stmt = obtenerConexion()->prepare("SELECT id, nombres FROM trabajador WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function obtenerTrabajadorPorDni(string $dni): ?array
{
    $stmt = obtenerConexion()->prepare("SELECT id, nombres FROM trabajador WHERE dni = ?");
    $stmt->execute([$dni]);
    return $stmt->fetch() ?: null;
}

function buscarJefeTrabajador(int $trabajadorId): ?array
{
    $stmt = obtenerConexion()->prepare(
        "SELECT
            u.id AS usuario_id,
            u.rol,
            j.id AS trabajador_id,
            j.nombres
        FROM trabajador t
        JOIN usuario u ON u.id = t.jefe_usuario_id AND u.estado = 'ACTIVO'
        JOIN trabajador j ON j.id = u.trabajador_id
        WHERE t.id = ?"
    );

    $stmt->execute([$trabajadorId]);
    return $stmt->fetch() ?: null;
}

function asignarJefeTrabajador(int $trabajadorId, int $jefeUsuarioId): void
{
    $stmt = obtenerConexion()->prepare('UPDATE trabajador SET jefe_usuario_id = ? WHERE id = ?');
    $stmt->execute([$jefeUsuarioId, $trabajadorId]);
}

function listarIdsTrabajadoresDeJefe(int $jefeUsuarioId): array
{
    $stmt = obtenerConexion()->prepare('SELECT id FROM trabajador WHERE jefe_usuario_id = ?');
    $stmt->execute([$jefeUsuarioId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

function crearTrabajador(
    int $empresaId, 
    ?int $empresaClienteId, 
    int $areaId, 
    ?int $cargoId, 
    string $dni, 
    string $nombres,
    ?string $cuentaBbva
): int
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO trabajador (empresa_id, empresa_cliente_id, area_id, cargo_id, dni, nombres, cuenta_bbva, cuenta_bbva_fecha_sincronizacion)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );

    $stmt->execute([$empresaId, $empresaClienteId, $areaId, $cargoId, $dni, $nombres, $cuentaBbva]);
    return (int)$pdo->lastInsertId();
}

function actualizarCuentaBbvaTrabajador(int $trabajadorId, string $cuentaBbva): void
{
    $stmt = obtenerConexion()->prepare('UPDATE trabajador SET cuenta_bbva = ? WHERE id = ?');
    $stmt->execute([$cuentaBbva, $trabajadorId]);
}
