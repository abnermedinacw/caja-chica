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
        if ($nivel['rol_requerido'] === 'JEFATURA') {
            $encargadoId = $jefe ? (int)$jefe['usuario_id'] : null;
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
            continue;
        }

        $encargados = obtenerEncargadosNivel((int)$nivel['id']);
        if (empty($encargados)) {
            return $nivel;
        }

        $encargadosIds = array_map('intval', array_column($encargados, 'id'));
        $disponibles = array_diff($encargadosIds, array_merge([$solicitanteId], $aprobadoresPrevios));

        if (!empty($disponibles)) {
            return $nivel;
        }

        $encargadoId = in_array($solicitanteId, $encargadosIds, true)
            ? $solicitanteId
            : (array_values(array_intersect($aprobadoresPrevios, $encargadosIds))[0] ?? $encargadosIds[0]);

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
            return ['ok' => false, 'error' => 'Este Resumen ya fue atendido por otro aprobador.'];
        }
        if (!puedeAtenderNivel($resumen, $usuarioId, $rolUsuario)) {
            return ['ok' => false, 'error' => 'Este nivel ya fue atendido por otro usuario o no tienes permiso para aprobarlo.'];
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
    if (trim($motivo) === '') {
        return ['ok' => false, 'error' => 'Indica motivo de rechazo.'];
    }

    $pdo = obtenerConexion();
    $pdo->beginTransaction();
    try {
        bloquearResumen($resumenId);
        $resumen = obtenerResumenAprobador($resumenId);
        if (!$resumen || $resumen['estado_general'] !== 'EN_REVISION') {
            return ['ok' => false, 'error' => 'Este Resumen ya fue atendido por otro aprobador.'];
        }
        if (!puedeAtenderNivel($resumen, $usuarioId, $rolUsuario)) {
            return ['ok' => false, 'error' => 'Este nivel ya fue atendido por otro usuario o no tienes permiso para rechazarlo.'];
        }

        registrarEventoResumen($resumenId, 'RECHAZADO', $usuarioId, (int)$resumen['nivel_actual_id'], $motivo);

        $nivelDestino = calcularSiguienteEscalonCascada((int)$resumen['nivel_actual_orden'], $resumenId);
        actualizarEstadoResumen($resumenId, 'RECHAZADO', $nivelDestino ? (int)$nivelDestino['id'] : null, $motivo);

        $pdo->commit();
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }

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
    $pdo = obtenerConexion();
    $pdo->beginTransaction();
    try {
        bloquearResumen($resumenId);
        $resumen = obtenerResumenAprobador($resumenId);
        if (!$resumen || $resumen['estado_general'] !== 'RECHAZADO' || $resumen['nivel_actual_id'] === null) {
            return ['ok' => false, 'error' => 'Este Resumen no tiene un rechazo pendiente de revisión o ya fue atendido.'];
        }
        if (!puedeAtenderNivel($resumen, $usuarioId, $rolUsuario)) {
            return ['ok' => false, 'error' => 'Este nivel ya fue atendido por otro usuario o no te corresponde revisar este rechazo.'];
        }

        registrarEventoResumen($resumenId, 'RECHAZO_REVISADO', $usuarioId, (int)$resumen['nivel_actual_id']);

        $nivelDestino = calcularSiguienteEscalonCascada((int)$resumen['nivel_actual_orden'], $resumenId);
        actualizarEstadoResumen($resumenId, 'RECHAZADO', $nivelDestino ? (int)$nivelDestino['id'] : null, $resumen['motivo_rechazo']);

        $pdo->commit();
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }

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
    if ((int)($resumen['solicitante_usuario_id'] ?? 0) === $usuarioId) {
        return false;
    }

    if (empty($resumen['nivel_actual_rol']) || empty($resumen['nivel_actual_id'])) {
        return false;
    }

    if ($resumen['nivel_actual_rol'] === 'JEFATURA') {
        return esJefeDelTrabajador($usuarioId, $resumen);
    }

    if ($resumen['nivel_actual_rol'] !== $rolUsuario) {
        return false;
    }

    $encargados = obtenerEncargadosNivel((int)$resumen['nivel_actual_id']);
    $idsEncargados = array_map('intval', array_column($encargados, 'id'));
    return in_array($usuarioId, $idsEncargados, true);
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
