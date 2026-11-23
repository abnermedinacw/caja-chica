<?php
require_once __DIR__ . '/../Datos/planillaMovilidad.php';
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/cajaChica.php';
require_once __DIR__ . '/validaciones.php';
require_once __DIR__ . '/../../config/database.php';

function guardarPlanillaMovilidad(int $trabajadorId, int $resumenId, array $datos, array $items, int $usuarioId = 0, string $rol = ''): array
{
    $acceso = resolverAccesoEdicionResumen($resumenId, $trabajadorId, $usuarioId, $rol);
    if (!$acceso['ok']) {
        return ['ok' => false, 'error' => $acceso['error']];
    }
    $resumen = $acceso['resumen'];
    $esContabilidad = $acceso['esContabilidad'];

    $existentePrevio = obtenerPlanillaPorResumen($resumenId);
    if ($esContabilidad && !$existentePrevio) {
        return ['ok' => false, 'error' => 'Contabilidad solo puede editar planillas ya registradas.'];
    }

    $periodoInicio = $datos['periodo_inicio'] ?? '';
    $periodoFin = $datos['periodo_fin'] ?? '';
    $fechaEmision = $datos['fecha_emision'] ?? '';

    if ($periodoInicio === '' || $periodoFin === '' || $fechaEmision === '') {
        return ['ok' => false, 'error' => 'Completa el periodo y la fecha de emisión.'];
    }
    if ($periodoFin < $periodoInicio) {
        return ['ok' => false, 'error' => 'El periodo fin no puede ser antes que el periodo inicio.'];
    }
    if ($periodoInicio < $resumen['periodo_inicio'] || $periodoFin > $resumen['periodo_fin']) {
        return ['ok' => false, 'error' => "El periodo de la planilla debe estar dentro del periodo del Resumen ({$resumen['periodo_inicio']} a {$resumen['periodo_fin']})."];
    }
    if ($error = validarFechaNoFutura($fechaEmision, 'emisión')) {
        return ['ok' => false, 'error' => $error];
    }
    if ($fechaEmision < $periodoFin) {
        return ['ok' => false, 'error' => 'La fecha de emisión no puede ser antes de que termine el periodo.'];
    }
    if (empty($items)) {
        return ['ok' => false, 'error' => 'Agrega al menos un viaje.'];
    }

    foreach ($items as $it) {
        if ($error = validarMonto($it['monto'] ?? null)) {
            return ['ok' => false, 'error' => $error];
        }
        if (empty($it['fecha']) || empty($it['motivo']) || empty($it['origen']) || empty($it['destino'])) {
            return ['ok' => false, 'error' => 'Completa fecha, motivo, origen y destino de cada viaje.'];
        }
        if ($error = validarFechaEnRango($it['fecha'], $periodoInicio, $periodoFin, 'el viaje')) {
            return ['ok' => false, 'error' => $error];
        }
    }

    $pdo = obtenerConexion();
    $pdo->beginTransaction();

    try {
        $existente = obtenerPlanillaPorResumen($resumenId);

        if ($existente) {
            $planillaId = (int)$existente['id'];
            $idsExistentes = obtenerDetallesIdsPlanilla($planillaId);
            if ($esContabilidad) {
                if ($error = validarMismosItemsContabilidad($items, $idsExistentes)) {
                    throw new InvalidArgumentException($error);
                }
            }
            actualizarCabeceraPlanilla($planillaId, $periodoInicio, $periodoFin, $fechaEmision);
        } else {
            $numero = sgteNumeroPlanillaMovilidad($trabajadorId);
            $planillaId = crearPlanillaBorrador($trabajadorId, $numero, $periodoInicio, $periodoFin, $fechaEmision, $resumenId);
            $idsExistentes = [];
        }

        $idsConservados = [];
        // Ver el comentario equivalente en documentoContable.php: correlaciona
        // la etiqueta temporal del navegador (_tempId) con el id real, en el
        // mismo momento en que se crea/actualiza cada detalle.
        $idsPorTempId = [];
        $item = 1;
        foreach ($items as $it) {
            $tipoEvidencia = $it['tipo_evidencia'] ?? 'NINGUNO';
            $observacionEvidencia = $it['observacion_evidencia'] ?? null;

            $detalleId = isset($it['id']) ? (int)$it['id'] : 0;
            if ($detalleId > 0 && in_array($detalleId, $idsExistentes, true)) {
                actualizarDetallePlanilla($detalleId, $item, $it['fecha'], $it['motivo'], $it['origen'], $it['destino'], (float)$it['monto'], $tipoEvidencia, $observacionEvidencia);
                $idsConservados[] = $detalleId;
                $idReal = $detalleId;
            } else {
                $idReal = insertarDetallePlanilla($planillaId, $item, $it['fecha'], $it['motivo'], $it['origen'], $it['destino'], (float)$it['monto'], $tipoEvidencia, $observacionEvidencia);
            }
            if (!empty($it['_tempId'])) {
                $idsPorTempId[$it['_tempId']] = $idReal;
            }
            $item++;
        }

        foreach (array_diff($idsExistentes, $idsConservados) as $detalleId) {
            eliminarDetallePlanillaPorId($detalleId);
        }

        recalcularTotalPlanilla($planillaId);
        sincronizarTotalesResumen($resumenId);
        if ($esContabilidad) {
            registrarEventoResumen($resumenId, 'EDITADO_CONTABILIDAD', $usuarioId, (int)$resumen['nivel_actual_id'], 'Corrección de ítems en Planilla de Movilidad');
        }
        $pdo->commit();
        return ['ok' => true, 'id' => $planillaId, 'items' => obtenerDetallePlanilla($planillaId), 'idsPorTempId' => $idsPorTempId];

    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'No se pudo guardar: ' . $e->getMessage()];
    }
}

function enlazarPlanillaExistente(int $trabajadorId, int $resumenId, int $planillaId): array
{
    $resumen = obtenerResumenPorId($resumenId, $trabajadorId);
    if (!$resumen || !esResumenEditable($resumen)) {
        return ['ok' => false, 'error' => 'Este Resumen no se puede editar.'];
    }
    if (obtenerPlanillaPorResumen($resumenId)) {
        return ['ok' => false, 'error' => 'Este Resumen ya tiene una Planilla de Movilidad enlazada.'];
    }

    $esValida = false;
    foreach (listarPlanillasDisponibles($trabajadorId) as $p) {
        if ((int)$p['id'] === $planillaId) {
            $esValida = true;
            break;
        }
    }
    if (!$esValida) {
        return ['ok' => false, 'error' => 'Esa Planilla no está disponible para enlazar.'];
    }

    enlazarPlanillaAResumen($planillaId, $resumenId);
    sincronizarTotalesResumen($resumenId);
    return ['ok' => true];
}

function quitarPlanillaDeResumen(int $resumenId, int $trabajadorId): array
{
    $resumen = obtenerResumenPorId($resumenId, $trabajadorId);
    if (!$resumen || !esResumenEditable($resumen)) {
        return ['ok' => false, 'error' => 'Este Resumen no se puede editar.'];
    }

    $planilla = obtenerPlanillaPorResumen($resumenId);
    if (!$planilla) {
        return ['ok' => false, 'error' => 'No hay Planilla de Movilidad enlazada a este Resumen.'];
    }

    desenlazarPlanilla((int)$planilla['id']);
    sincronizarTotalesResumen($resumenId);
    return ['ok' => true];
}
