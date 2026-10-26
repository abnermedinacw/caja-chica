<?php

require_once __DIR__ . '/../../config/database.php';

function sgteNumeroResumen(int $trabajadorId, int $empresaId): int
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare("SELECT MAX(numero) AS maximo FROM caja_chica WHERE trabajador_id = ? AND empresa_id = ?");
    $stmt->execute([$trabajadorId, $empresaId]);
    $fila = $stmt->fetch();
    return ((int)($fila['maximo'] ?? 0)) + 1;
}

function crearBorradorResumen(
    int $trabajadorId,
    int $empresaId,
    ?int $fondoId,
    int $numero,
    string $periodoInicio,
    string $periodoFin,
    string $fechaEmision,
    float $montoRendicion,
    float $saldoInicial
): int
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO caja_chica
            (numero, fondo_id, trabajador_id, empresa_id, periodo_inicio, periodo_fin, fecha_emision, monto_rendicion, saldo_inicial, estado_general)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'BORRADOR')"
    );
    $stmt->execute([$numero, $fondoId, $trabajadorId, $empresaId, $periodoInicio, $periodoFin, $fechaEmision, $montoRendicion, $saldoInicial]);
    return (int)$pdo->lastInsertId();
}

function obtenerResumenPorId(int $id, int $trabajadorId): ?array
{
    $pdo = obtenerConexion();
    $sql =
        "SELECT
            cc.*,
            f.monto_asignado,
            e.razon_social AS empresa,
            e.ruc AS empresa_ruc,
            e.sello_ruta,
            t.nombres,
            t.dni,
            a.nombre AS area_nombre,
            na.nombre AS nivel_actual_nombre,
            na.rol_requerido AS nivel_actual_rol
        FROM caja_chica cc
        JOIN empresa e ON e.id = cc.empresa_id
        JOIN trabajador t ON t.id = cc.trabajador_id
        JOIN area a ON a.id = t.area_id
        LEFT JOIN fondo f ON f.id = cc.fondo_id
        LEFT JOIN nivel_aprobacion na ON na.id = cc.nivel_actual_id
        WHERE cc.id = ? AND cc.trabajador_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $trabajadorId]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

function listarResumenesPorTrabajador(int $trabajadorId, ?int $limite = null): array
{
    $pdo = obtenerConexion();
    $sql =
        "SELECT 
            cc.id, 
            cc.numero,
            cc.periodo_inicio,
            cc.periodo_fin,
            cc.fecha_emision,
            cc.total_egresos,
            cc.estado_general,
            cc.liquidado,
            e.razon_social AS empresa,
            na.nombre AS nivel_actual_nombre
        FROM caja_chica cc
        JOIN empresa e ON e.id = cc.empresa_id
        LEFT JOIN nivel_aprobacion na ON na.id = cc.nivel_actual_id
        WHERE cc.trabajador_id = ?
        ORDER BY cc.fecha_emision DESC, cc.id DESC";

    if ($limite !== null) $sql .= ' LIMIT ?';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $trabajadorId, PDO::PARAM_INT);
    if ($limite !== null) $stmt->bindValue(2, max(1, $limite), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function actualizarCabeceraResumen(int $id, string $periodoInicio, string $periodoFin, string $fechaEmision): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "UPDATE caja_chica SET periodo_inicio = ?, periodo_fin = ?, fecha_emision = ? WHERE id = ?"
    );
    $stmt->execute([$periodoInicio, $periodoFin, $fechaEmision, $id]);
}

// Suma delo que aportan los 3 documentos enlazados
function recalcularTotalEgresosResumen(int $resumenId): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "UPDATE caja_chica cc
        SET total_egresos =
            IFNULL((SELECT SUM(dcd.monto) FROM documento_contable_detalle dcd
                    JOIN documento_contable dc ON dc.id = dcd.documento_contable_id
                    WHERE dc.resumen_id = cc.id), 0)
            + IFNULL((SELECT SUM(dncd.monto) FROM documento_no_contable_detalle dncd
                    JOIN documento_no_contable dnc ON dnc.id = dncd.documento_no_contable_id
                    WHERE dnc.resumen_id = cc.id), 0)
            + IFNULL((SELECT SUM(pm.total) FROM planilla_movilidad pm
                    WHERE pm.resumen_id = cc.id), 0)
        WHERE cc.id = ?"
    );
    $stmt->execute([$resumenId]);
}

function actualizarEstadoResumen(int $id, string $estadoGeneral, ?int $nivelActualId, ?string $motivoRechazo = null): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "UPDATE caja_chica SET estado_general = ?, nivel_actual_id = ?, motivo_rechazo = ? WHERE id = ?"
    );

    $stmt->execute([$estadoGeneral, $nivelActualId, $motivoRechazo, $id]);
}

function cerrarResumen(int $id, float $saldoFinal, bool $liquidado, ?string $fechaLiquidacion): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "UPDATE caja_chica SET saldo_final = ?, liquidado = ?, fecha_liquidacion = ? WHERE id = ?"
    );
    $stmt->execute([$saldoFinal, $liquidado ? 1 : 0, $fechaLiquidacion, $id]);
}

function registrarEventoResumen(int $resumenId, string $evento, int $usuarioId, ?int $nivelId = null, ?string $motivo = null): void
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "INSERT INTO evento_caja_chica (caja_chica_id, nivel_id, evento, usuario_id, motivo)VALUES (?,?,?,?,?)"
    );
    $stmt->execute([$resumenId, $nivelId, $evento, $usuarioId, $motivo]);
}

function contarRevisionesUsuarioNivel(int $resumenId, int $usuarioId, int $nivelId): int
{
    $stmt = obtenerConexion()->prepare(
        "SELECT COUNT(*)
        FROM evento_caja_chica
        WHERE caja_chica_id = ?
          AND usuario_id = ?
          AND nivel_id = ?
          AND evento IN ('APROBADO', 'RECHAZADO', 'RECHAZO_REVISADO')"
    );
    $stmt->execute([$resumenId, $usuarioId, $nivelId]);
    return (int)$stmt->fetchColumn();
}

function registrarAutovalidacionNivel(
    int $resumenId,
    int $nivelId,
    int $usuarioId
): void
{
    $pdo = obtenerConexion();

    $stmt = $pdo->prepare(
        "SELECT EXISTS (
            SELECT 1
            FROM evento_caja_chica ev
            WHERE ev.caja_chica_id = ?
                AND ev.nivel_id = ?
                AND ev.evento IN ('APROBADO', 'AUTO_VALIDADO')
                AND ev.id > COALESCE((
                    SELECT MAX(env.id)
                    FROM evento_caja_chica env
                    WHERE env.caja_chica_id = ?
                        AND env.evento = 'ENVIADO'
                    ),0)
        )"
    );

    $stmt->execute([
        $resumenId,
        $nivelId,
        $resumenId
    ]);

    if ((bool)$stmt->fetchColumn()) return;

    registrarEventoResumen(
        $resumenId,
        'AUTO_VALIDADO',
        $usuarioId,
        $nivelId,
        'Nivel cubierto por usuario asignado o aprobación previa.'
    );
}

function obtenerEventosResumen(int $resumenId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT
            ev.evento,
            ev.motivo,
            ev.fecha,
            na.nombre AS nivel,
            na.orden AS orden,
            t.nombres AS usuario
            FROM evento_caja_chica ev
            LEFT JOIN nivel_aprobacion na ON na.id = ev.nivel_id
            JOIN usuario u ON u.id = ev.usuario_id
            LEFT JOIN trabajador t ON t.id = u.trabajador_id
            WHERE ev.caja_chica_id = ?
                AND ev.evento NOT IN ('TXT_DESCARGADO', 'TXT_REACTIVADO')
            ORDER BY ev.fecha"
    );
    $stmt->execute([$resumenId]);
    return $stmt->fetchAll();
}

function listarResumenesPorNivel(int $nivelId): array
{
    $pdo = obtenerConexion();
    $sql =
        "SELECT
            cc.id, cc.numero, cc.fecha_emision, cc.total_egresos,
            e.razon_social AS empresa,
            t.nombres AS solicitante
        FROM caja_chica cc
        JOIN empresa e ON e.id = cc.empresa_id
        JOIN trabajador t ON t.id = cc.trabajador_id
        WHERE cc.estado_general = 'EN_REVISION' AND cc.nivel_actual_id = ?
        ORDER BY cc.fecha_emision";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nivelId]);
    return $stmt->fetchAll();
}

function listarResumenesPorUsuario(int $usuarioId, string $rol): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT
            cc.id,
            cc.numero,
            cc.fecha_emision,
            cc.total_egresos,
            cc.estado_general,
            e.razon_social AS empresa,
            t.nombres AS solicitante,
            na.nombre AS etapa
        FROM caja_chica cc
        JOIN empresa e ON e.id = cc.empresa_id
        JOIN trabajador t ON t.id = cc.trabajador_id
        JOIN nivel_aprobacion na ON na.id = cc.nivel_actual_id
        JOIN usuario u ON u.id = na.aprobador_usuario_id
        WHERE na.aprobador_usuario_id = ?
            AND na.rol_requerido = ?
            AND u.estado = 'ACTIVO'
            AND cc.estado_general IN ('EN_REVISION', 'RECHAZADO')
        ORDER BY cc.fecha_emision" 
    );

    $stmt->execute([$usuarioId, $rol]);
    return $stmt->fetchAll();
}

function listarResumenesPorFondo(int $fondoId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT 
            id,
            numero,
            periodo_inicio,
            periodo_fin,
            total_egresos,
            estado_general
        FROM caja_chica
        WHERE fondo_id = ?
        ORDER BY fecha_emision DESC"
    );

    $stmt->execute([$fondoId]);
    return $stmt->fetchAll();
}

function listarResumenesJefaturas(int $jefeUsuarioId): array
{
    $stmt = obtenerConexion()->prepare(
        "SELECT
            cc.id,
            cc.numero,
            cc.fecha_emision,
            cc.total_egresos,
            cc.estado_general,
            e.razon_social AS empresa,
            t.nombres AS solicitante,
            na.nombre AS etapa
        FROM caja_chica cc
        JOIN empresa e ON e.id = cc.empresa_id
        JOIN trabajador t ON t.id = cc.trabajador_id
        JOIN nivel_aprobacion na ON na.id = cc.nivel_actual_id
        WHERE na.rol_requerido = 'JEFATURA'
            AND cc.estado_general IN ('EN_REVISION', 'RECHAZADO')
            AND t.jefe_usuario_id = ?
        ORDER BY cc.fecha_emision"
    );
    $stmt->execute([$jefeUsuarioId]);
    return $stmt->fetchAll();
}

// Niveles (por "orden") que ya aprobaron este Resumen en algún momento —
// es exactamente el criterio que se usa para decidir por cuáles niveles
// intermedios debe pasar un rechazo al bajar de vuelta hacia el
// trabajador: solo los que de verdad participaron, no todos los que
// existen entre medio.
function obtenerOrdenesAprobadosResumen(int $resumenId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT DISTINCT na.orden
        FROM evento_caja_chica ev
        JOIN nivel_aprobacion na ON na.id = ev.nivel_id
        WHERE ev.caja_chica_id = ? AND ev.evento = 'APROBADO'
        ORDER BY na.orden DESC"
    );
    $stmt->execute([$resumenId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'orden'));
}

function obtenerAdjuntosResumen(int $resumenId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT nombre_archivo, ruta FROM adjunto_caja_chica WHERE caja_chica_id = ? ORDER BY fecha_subida"
    );
    $stmt->execute([$resumenId]);
    return $stmt->fetchAll();
}

function listarResumenesResueltosPorUsuario(int $usuarioId): array
{
    $pdo = obtenerConexion();
    $sql =
        "SELECT  
            cc.id,
            cc.numero, 
            cc.fecha_emision,
            cc.total_egresos,
            cc.estado_general,
            e.razon_social AS empresa,
            t.nombres AS solicitante,
            ev.evento AS accion,
            ev.fecha AS fecha_accion
        FROM evento_caja_chica ev
        JOIN caja_chica cc ON cc.id = ev.caja_chica_id
        JOIN empresa e ON e.id = cc.empresa_id
        JOIN trabajador t ON t.id = cc.trabajador_id
        WHERE ev.usuario_id = ? AND ev.evento IN ('APROBADO', 'RECHAZADO')
        ORDER BY ev.fecha DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuarioId]);
    return $stmt->fetchAll();
}

// Obtiene los usuarios que aprobaron un resumen específico
function obtenerUsuariosAprobadoresResumen(int $resumenId): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT DISTINCT ev.usuario_id
        FROM evento_caja_chica ev
        WHERE ev.caja_chica_id = ?
        AND ev.evento IN ('APROBADO', 'AUTO_VALIDADO')
        AND ev.id > COALESCE(
            (
                SELECT MAX(env.id)
                FROM evento_caja_chica env
                WHERE env.caja_chica_id = ?
                  AND env.evento = 'ENVIADO'
            ),
            0
        )"
    );
    $stmt->execute([$resumenId, $resumenId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'usuario_id'));
}

// Se usa dentro de una transacción antes de validar un cambio de estado.
function bloquearResumen(int $id): void
{
    $stmt = obtenerConexion()->prepare('SELECT id FROM caja_chica WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $stmt->fetchColumn();
}

function obtenerResumenAprobador(int $id): ?array
{
    $pdo = obtenerConexion();
    $sql = 
        "SELECT
            cc.*,
            f.id AS fondo_id,
            f.tipo AS fondo_tipo,
            f.monto_asignado,
            e.razon_social AS empresa,
            e.ruc AS empresa_ruc,
            e.sello_ruta,
            t.id AS trabajador_id,
            t.nombres,
            t.dni,
            t.area_id,
            a.nombre AS area_nombre,
            u.id AS solicitante_usuario_id,
            u.rol AS solicitante_rol,
            na.nombre AS nivel_actual_nombre,
            na.orden AS nivel_actual_orden,
            na.rol_requerido AS nivel_actual_rol,
            na.codigo AS nivel_actual_codigo,
            na.aprobador_usuario_id AS nivel_actual_aprobador_id,
            encargado.estado AS nivel_actual_aprobador_estado,
            encargado.rol AS nivel_actual_aprobador_rol
        FROM caja_chica cc
        JOIN empresa e ON e.id = cc.empresa_id
        JOIN trabajador t ON t.id = cc.trabajador_id
        JOIN area a ON a.id = t.area_id
        JOIN usuario u ON u.trabajador_id = t.id
        LEFT JOIN fondo f ON f.id = cc.fondo_id
        LEFT JOIN nivel_aprobacion na ON na.id = cc.nivel_actual_id
        LEFT JOIN usuario encargado ON encargado.id = na.aprobador_usuario_id
        WHERE cc.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

// Obtener rangos de fechas definidos en los documentos para no alterar la cabecera
function obtenerRangosFechasDocumentos(int $resumenId): ?array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT 
            MIN(periodo_inicio) AS inicio_minimo,
            MAX(periodo_fin) AS fin_maximo,
            MAX(fecha_documento) AS fecha_documento_maxima
        FROM (
            SELECT 
                periodo_inicio, 
                periodo_fin,
                fecha_rendicion AS fecha_documento
            FROM documento_contable
            WHERE resumen_id = ?

            UNION ALL

            SELECT 
                periodo_inicio, 
                periodo_fin,
                fecha_rendicion AS fecha_documento
            FROM documento_no_contable
            WHERE resumen_id = ?

            UNION ALL

            SELECT 
                periodo_inicio, 
                periodo_fin,
                fecha_emision AS fecha_documento
            FROM planilla_movilidad
            WHERE resumen_id = ?
        ) documentos"
    );

    $stmt->execute([$resumenId, $resumenId, $resumenId]);
    $rango = $stmt->fetch();
    
    if (!$rango || $rango['inicio_minimo'] === null) return null;
    return $rango;
}

// Saber si un usuario participó de una aprobacion
// Usado para la visualizacion del resumen por parte de un aprobador
function usuarioParticipo(int $resumenId, int $usuarioId): bool 
{
    $pdo = obtenerConexion();

    $stmt = $pdo->prepare(
        "SELECT EXISTS (
            SELECT 1
            FROM evento_caja_chica
            WHERE caja_chica_id = ?
                AND usuario_id = ?
                AND evento IN ('APROBADO', 'AUTO_VALIDADO','RECHAZADO', 'RECHAZO_REVISADO')
        )"
    );
    $stmt->execute([$resumenId, $usuarioId]);
    return (bool)$stmt->fetchColumn();
}
