<?php
require_once __DIR__ . '/../../config/database.php';

// Obtener imagenes de firmas
function obtenerFirmaPersonal(int $usuarioId): ?array
{
    $pdo = obtenerConexion();

    $stmt = $pdo->prepare(
        "SELECT
            id,
            ruta_imagen,
            fecha_registro,
            fecha_actualizacion
        FROM firma_digital
        WHERE tipo = 'PERSONAL' AND usuario_id = ?
        ORDER BY id DESC
        LIMIT 1"
    );

    $stmt->execute([$usuarioId]);
    $firma = $stmt->fetch();

    return $firma ?: null;
}

function obtenerFirmaGerencia(int $usuarioId, int $empresaId): ?array
{
    $pdo = obtenerConexion();

    $stmt = $pdo->prepare(
        "SELECT 
            id, 
            ruta_imagen,
            fecha_registro,
            fecha_actualizacion
        FROM firma_digital
        WHERE tipo = 'GERENCIA'
            AND usuario_id = ?
            AND empresa_id = ?
        ORDER BY id DESC
        LIMIT 1"
    );

    $stmt->execute([$usuarioId, $empresaId]);
    return $stmt->fetch() ?: null;
}

// Contabilidad no le pertenece a un usuario especifico ni empresa
// Se valida por empresaId en caso las rubricas en un futuro sean por empresa
function obtenerFirmaContabilidad(int $empresaId): ?array 
{
    $pdo = obtenerConexion();

    $stmt = $pdo->prepare(
        "SELECT 
            id,
            ruta_imagen,
            fecha_registro,
            fecha_actualizacion
        FROM firma_digital
        WHERE tipo = 'CONTABILIDAD' 
            AND usuario_id IS NULL
            AND (empresa_id = ? OR empresa_id IS NULL)
        ORDER BY 
            CASE WHEN empresa_id = ? THEN 0 ELSE 1 END,
            id DESC
        LIMIT 1"
    );

    $stmt->execute([$empresaId, $empresaId]);
    $firma = $stmt->fetch();
    return $firma ?: null;
}

function guardarRutaFirmaPersonal(int $usuarioId, string $ruta): void 
{
    $pdo = obtenerConexion();

    $stmt = $pdo->prepare(
        "SELECT id 
        FROM firma_digital 
        WHERE tipo = 'PERSONAL' 
            AND usuario_id = ?
        ORDER BY id DESC
        LIMIT 1" 
    );

    $stmt->execute([$usuarioId]);
    $firmaId = $stmt->fetchColumn();

    if ($firmaId) {
        $stmt = $pdo->prepare(
            "UPDATE firma_digital
            SET ruta_imagen = ?,
                fecha_actualizacion = NOW()
            WHERE id = ?"
        );

        $stmt->execute([$ruta, $firmaId]);
        return;
    }
    
    $stmt = $pdo->prepare(
        "INSERT INTO firma_digital (
            tipo,
            usuario_id,
            empresa_id,
            ruta_imagen
        )
        VALUES ('PERSONAL', ?, NULL, ?)"
    );

    $stmt->execute([$usuarioId, $ruta]); 
}

// Obtener firma de aprobadores en el proceso
function obtenerFirmanteSolicitantePdf(int $resumenId): ?array
{
    $pdo = obtenerConexion();

    $stmt = $pdo->prepare(
        "SELECT
            u.id AS usuario_id,
            COALESCE(t.nombres, u.username) AS nombre,
            ev.fecha,
            ev.evento
        FROM evento_caja_chica ev
        JOIN usuario u ON u.id = ev.usuario_id
        LEFT JOIN trabajador t ON t.id = u.trabajador_id
        WHERE ev.caja_chica_id = ?
            AND ev.evento = 'ENVIADO'
        ORDER BY ev.id DESC
        LIMIT 1"
    );

    $stmt->execute([$resumenId]);
    return $stmt->fetch() ?: null;
}

// Develve aprobaciones del ciclo iniciado por el último estado de ENVIADO
function obtenerFirmanteNivelesPdf(int $resumenId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT 
            na.codigo,
            na.nombre AS nivel_nombre,
            ev.evento,
            ev.fecha,
            u.id AS usuario_id,
            COALESCE(t.nombres, u.username) AS nombre
        FROM evento_caja_chica ev
        JOIN nivel_aprobacion na ON na.id = ev.nivel_id
        JOIN usuario u ON u.id = ev.usuario_id
        LEFT JOIN trabajador t ON t.id = u.trabajador_id
        WHERE ev.caja_chica_id = ?
            AND ev.evento IN ('APROBADO', 'AUTO_VALIDADO')
            AND ev.id > COALESCE((
                SELECT MAX(env.id)
                FROM evento_caja_chica env
                WHERE env.caja_chica_id = ?
                    AND env.evento = 'ENVIADO'
            ), 0)
        ORDER BY ev.id"
    );

    $stmt->execute([$resumenId, $resumenId]);
    return $stmt->fetchAll();
}
