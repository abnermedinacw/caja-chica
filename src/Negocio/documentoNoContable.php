<?php
require_once __DIR__ . '/../Datos/documentoNoContable.php';
require_once __DIR__ . '/../Datos/adjuntos.php';
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/cajaChica.php';
require_once __DIR__ . '/validaciones.php';
require_once __DIR__ . '/../../config/database.php';

function guardarDocumentoNoContable(
    int $trabajadorId,
    int $areaId,
    int $resumenId,
    array $datos,
    array $items
): array {
    $periodoInicio = $datos['periodo_inicio'] ?? '';
    $periodoFin = $datos['periodo_fin'] ?? '';
    $fechaRendicion = $datos['fecha_rendicion'] ?? '';

    $resumen = obtenerResumenPorId($resumenId, $trabajadorId);
    if (!$resumen) {
        return ['ok' => false, 'error' => 'Resumen no encontrado.'];
    }
    if (!esResumenEditable($resumen)) {
        return ['ok' => false, 'error' => 'Este Resumen ya está en proceso de aprobación y no se puede editar.'];
    }
    if ($periodoInicio === '' || $periodoFin === '' || $fechaRendicion === '') {
        return ['ok' => false, 'error' => 'Completa periodo y fecha de rendición.'];
    }
    if ($periodoFin < $periodoInicio) {
        return ['ok' => false, 'error' => 'El periodo fin no puede ser antes que el periodo de inicio.'];
    }
    if ($periodoInicio < $resumen['periodo_inicio'] || $periodoFin > $resumen['periodo_fin']) {
        return ['ok' => false, 'error' => "El periodo del documento debe estar dentro del periodo del Resumen ({$resumen['periodo_inicio']} a {$resumen['periodo_fin']})."];
    }
    if ($error = validarFechaNoFutura($fechaRendicion, 'rendición')) {
        return ['ok' => false, 'error' => $error];
    }
    if ($fechaRendicion < $periodoFin) {
        return ['ok' => false, 'error' => 'La fecha de rendición no puede ser antes de que termine el periodo.'];
    }
    if (empty($items)) {
        return ['ok' => false, 'error' => 'Agrega al menos un item.'];
    }

    foreach ($items as $it) {
        if ($error = validarMonto($it['monto'] ?? null)) {
            return ['ok' => false, 'error' => $error];
        }
        if (empty($it['tipo_documento_id']) || empty($it['descripcion']) || empty($it['fecha_documento'])) {
            return ['ok' => false, 'error' => 'Cada item necesita tipo, descripción y fecha.'];
        }
        if ($error = validarFechaEnRango($it['fecha_documento'], $periodoInicio, $periodoFin, 'el item')) {
            return ['ok' => false, 'error' => $error];
        }
    }

    $pdo = obtenerConexion();
    $pdo->beginTransaction();

    try {
       $existente = obtenerDocumentoNoContablePorResumen($resumenId);

       if ($existente) {
        $documentoId = (int)$existente['id'];
        $idsExistentes = obtenerDetallesIdsDocumentoNoContable($documentoId);
       } else {
        $numero = sgteNumeroDocumentoNoContable($trabajadorId);
        $documentoId = crearDocumentoNoContable($trabajadorId, $areaId, $numero, $periodoInicio, $periodoFin, $fechaRendicion, $resumenId);
        $idsExistentes = [];
       }

       $idsConservados = [];
       // Ver el comentario equivalente en documentoContable.php: correlaciona
       // la etiqueta temporal del navegador (_tempId) con el id real, en el
       // mismo momento en que se crea/actualiza cada detalle.
       $idsPorTempId = [];
       $item = 1;
       foreach ($items as $it) {
        $numeroPaginas = isset($it['numero_paginas']) && $it['numero_paginas'] !== '' ? (int)$it['numero_paginas'] : null;
        $observaciones = $it['observaciones'] ?? null;

        $detalleId = isset($it['id']) ? (int)$it['id'] : 0;
        if ($detalleId > 0 && in_array($detalleId, $idsExistentes, true)) {
            actualizarDetalleDocumentoNoContable($detalleId, $item, (int)$it['tipo_documento_id'], $it['descripcion'], $it['fecha_documento'], $numeroPaginas, $observaciones, (float)$it['monto']);
            $idsConservados[] = $detalleId;
            $idReal = $detalleId;
        } else {
            $idReal = insertarDetalleDocumentoNoContable($documentoId, $item, (int)$it['tipo_documento_id'], $it['descripcion'], $it['fecha_documento'], $numeroPaginas, $observaciones, (float)$it['monto']);
        }
        if (!empty($it['_tempId'])) {
            $idsPorTempId[$it['_tempId']] = $idReal;
        }
        $item++;
       }

       foreach (array_diff($idsExistentes, $idsConservados) as $detalleId) {
        eliminarDetalleDocumentoNoContablePorId($detalleId);
       }

       sincronizarTotalesResumen($resumenId);
       $pdo->commit();
       return ['ok' => true, 'id' => $documentoId, 'items' => obtenerDetalleDocumentoNoContable($documentoId), 'idsPorTempId' => $idsPorTempId];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'No se pudo guardar: ' . $e->getMessage()];
    }
}

function eliminarDocumentoNoContableDeResumen(int $resumenId, int $trabajadorId): array
{
    $resumen = obtenerResumenPorId($resumenId, $trabajadorId);
    if (!$resumen || !esResumenEditable($resumen)) {
        return ['ok' => false, 'error' => 'Este Resumen no se puede editar.'];
    }

    $existente = obtenerDocumentoNoContablePorResumen($resumenId);
    if (!$existente) {
        return ['ok' => false, 'error' => 'No hay Documento No Contable enlazado a este Resumen.'];
    }

    $documentoId = (int)$existente['id'];
    $pdo = obtenerConexion();
    $pdo->beginTransaction();
    try {
        $rutas = eliminarAdjuntosDocumentoNoContable($documentoId);
        foreach (obtenerDetallesIdsDocumentoNoContable($documentoId) as $detalleId) {
            eliminarDetalleDocumentoNoContablePorId($detalleId);
        }
        eliminarDocumentoNoContablePorId($documentoId);
        sincronizarTotalesResumen($resumenId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'No se pudo quitar el documento.'];
    }

    eliminarArchivosAdjuntos($rutas);
    return ['ok' => true];
}
