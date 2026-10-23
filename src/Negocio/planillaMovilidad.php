<?php
require_once __DIR__ . '/../Datos/planillaMovilidad.php';
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/cajaChica.php';
require_once __DIR__ . '/validaciones.php';
require_once __DIR__ . '/../../config/database.php';

function guardarPlanillaMovilidad(int $trabajadorId, int $resumenId, array $datos, array $items): array
{
    $periodoInicio = $datos['periodo_inicio'] ?? '';
    $periodoFin = $datos['periodo_fin'] ?? '';
    $fechaEmision = $datos['fecha_emision'] ?? '';

    $resumen = obtenerResumenPorId($resumenId, $trabajadorId);
    if (!$resumen) {
        return ['ok' => false, 'error' => 'Resumen no encontrado.'];
    }
    if (!esResumenEditable($resumen)) {
        return ['ok' => false, 'error' => 'Este Resumen ya está en proceso de aprobación y no se puede editar.'];
    }
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
            actualizarCabeceraPlanilla($planillaId, $periodoInicio, $periodoFin, $fechaEmision);
            $idsExistentes = obtenerDetallesIdsPlanilla($planillaId);
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
