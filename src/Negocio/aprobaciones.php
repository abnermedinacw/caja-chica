<?php
require_once __DIR__ . '/../Datos/nivelAprobacion.php';
require_once __DIR__ . '/../Datos/cargos.php';
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/cajaChica.php';

// Funcion intermedia que encapsula la lógica de determinar si se debe avanzar al siguiente nivel o cerrar el resumen, dependiendo de si hay un nivel de parada o no.
function calcularNivelDeParada(
    int $resumenId,
    int $ordenDesde,
    array $resumen
): ?array
{
    $solicitanteId = (int)$resumen['solicitante_usuario_id'];
    $jefe = buscarJefeTrabajador((int)$resumen['trabajador_id']);
    $aprobadoresPrevios = obtenerUsuariosAprobadoresResumen($resumenId);
    $orden = $ordenDesde;

    while ($nivel = obtenerNivelPorOrden($orden)) {
        $encargadoId = $nivel['rol_requerido'] === 'JEFATURA'
            ? ($jefe ? (int)$jefe['usuario_id'] : null)
            : ($nivel['aprobador_usuario_id'] !== null ? (int)$nivel['aprobador_usuario_id'] : null);

        // Si no hay una asignación de todas formas no se debe intepretar como un nivel aprobado automaticamente
        if ($encargadoId === null) return $nivel;
        $nivelCubierto = $encargadoId === $solicitanteId || in_array($encargadoId, $aprobadoresPrevios, true);

        if (!$nivelCubierto) return $nivel;

        registrarAutovalidacionNivel(
            $resumenId,
            (int)$nivel['id'],
            $encargadoId
        );

        $aprobadoresPrevios[] = $encargadoId;
        $orden++;
    }

    return null;
}

// Aprueba nivel actual y avanza
function aprobarResumen(int $resumenId, int $usuarioId, string $rolUsuario): array
{
    $pdo = obtenerConexion();
    $pdo->beginTransaction();
    try {
        bloquearResumen($resumenId);
        $resumen = obtenerResumenAprobador($resumenId);
        if (!$resumen || $resumen['estado_general'] !== 'EN_REVISION') {
            return ['ok' => false, 'error' => 'Este Resumen no está pendiente de aprobación.'];
        }
        if (!puedeAtenderNivel($resumen, $usuarioId, $rolUsuario)) {
            return ['ok' => false, 'error' => 'No tienes permiso para aprobar este nivel.'];
        }
        registrarEventoResumen($resumenId, 'APROBADO', $usuarioId, (int)$resumen['nivel_actual_id']);

        $siguienteNivel = calcularNivelDeParada($resumenId, (int)$resumen['nivel_actual_orden'] + 1, $resumen);

        avanzarOCerrarResumen($resumenId, $resumen, $siguienteNivel);
        $pdo->commit();
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }

    return ['ok' => true];
}

function rechazarResumen(int $resumenId, int $usuarioId, string $rolUsuario, string $motivo): array
{
    $resumen = obtenerResumenAprobador($resumenId);
    if (!$resumen || $resumen['estado_general'] !== 'EN_REVISION') {
        return ['ok' => false, 'error' => 'Este Resumen no está pendiente de aprobación.'];
    }
    if (!puedeAtenderNivel($resumen, $usuarioId, $rolUsuario)) {
        return ['ok' => false, 'error' => 'No tienes permiso para rechazar este nivel.'];
    }
    if (trim($motivo) === '') {
        return ['ok' => false, 'error' => 'Indica motivo de rechazo.'];
    }

    registrarEventoResumen($resumenId, 'RECHAZADO', $usuarioId, (int)$resumen['nivel_actual_id'], $motivo);

    $nivelDestino = calcularSiguienteEscalonCascada((int)$resumen['nivel_actual_orden'], $resumenId);
    actualizarEstadoResumen($resumenId, 'RECHAZADO', $nivelDestino ? (int)$nivelDestino['id'] : null, $motivo);

    return ['ok' => true];
}

function calcularSiguienteEscalonCascada(int $ordenActual, int $resumenId): ?array
{
    foreach (obtenerOrdenesAprobadosResumen($resumenId) as $orden) {
        if ($orden < $ordenActual) {
            return obtenerNivelPorOrden($orden);
        }
    }
    return null;
}

function continuarCascadaRechazo(int $resumenId, int $usuarioId, string $rolUsuario): array
{
    $resumen = obtenerResumenAprobador($resumenId);
    if (!$resumen || $resumen['estado_general'] !== 'RECHAZADO' || $resumen['nivel_actual_id'] === null) {
        return ['ok' => false, 'error' => 'Este Resumen no tiene un rechazo pendiente de revisión.'];
    }
    if (!puedeAtenderNivel($resumen, $usuarioId, $rolUsuario)) {
        return ['ok' => false, 'error' => 'No te corresponde revisar este rechazo.'];
    }

    registrarEventoResumen($resumenId, 'RECHAZO_REVISADO', $usuarioId, (int)$resumen['nivel_actual_id']);

    $nivelDestino = calcularSiguienteEscalonCascada((int)$resumen['nivel_actual_orden'], $resumenId);
    actualizarEstadoResumen($resumenId, 'RECHAZADO', $nivelDestino ? (int)$nivelDestino['id'] : null, $resumen['motivo_rechazo']);

    return ['ok' => true];
}

function avanzarOCerrarResumen(int $resumenId, array $resumen, ?array $nivelDestino): void
{
    if (!$nivelDestino) {
        actualizarEstadoResumen($resumenId, 'VISADO', null);
        registrarSaldoFinal($resumenId, $resumen);
    } else {
        actualizarEstadoResumen($resumenId, 'EN_REVISION', (int)$nivelDestino['id']);
    }
}

function esJefeDelTrabajador(int $usuarioId, array $resumen): bool
{
    $jefe = buscarJefeTrabajador((int)$resumen['trabajador_id']);
    return $jefe !== null && (int)$jefe['usuario_id'] === $usuarioId;
}

function puedeAtenderNivel(array $resumen, int $usuarioId, string $rolUsuario): bool
{
    if ($resumen['nivel_actual_rol'] === 'JEFATURA') return esJefeDelTrabajador($usuarioId, $resumen);

    return (int)($resumen['nivel_actual_aprobador_id'] ?? 0) === $usuarioId
        && $resumen['nivel_actual_rol'] === $rolUsuario
        && $resumen['nivel_actual_aprobador_estado'] === 'ACTIVO'
        && $resumen['nivel_actual_aprobador_rol'] === $rolUsuario;
}

function puedeVerDocumentosResumen(
    array $resumen, 
    int $trabajadorIdSesion, 
    string $rolSesion, 
    int $usuarioIdSesion
): bool {
    if ((int)$resumen['trabajador_id'] === $trabajadorIdSesion) return true;
    if (usuarioParticipo((int)$resumen['id'], $usuarioIdSesion)) return true;

    $estadosValidos = ['EN_REVISION', 'RECHAZADO'];
    return in_array(
        $resumen['estado_general'], 
        $estadosValidos, 
        true
    ) && puedeAtenderNivel(
        $resumen, 
        $usuarioIdSesion, 
        $rolSesion
    );
}

function esResumenEditable(array $resumen): bool
{
    if ($resumen['estado_general'] === 'BORRADOR') {
        return true;
    }
    return $resumen['estado_general'] === 'RECHAZADO' && $resumen['nivel_actual_id'] === null;
}
