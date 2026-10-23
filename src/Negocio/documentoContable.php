<?php
require_once __DIR__ . '/../Datos/documentoContable.php';
require_once __DIR__ . '/../Datos/adjuntos.php';
require_once __DIR__ . '/../Datos/comprobantes.php';
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/cajaChica.php';
require_once __DIR__ . '/validaciones.php';
require_once __DIR__ . '/../../config/database.php';

function guardarDocumentoContable(
    int $trabajadorId,
    int $areaId,
    int $resumenId,
    array $datos,
    array $items
): array
{
    $periodoInicio = $datos['periodo_inicio'] ?? '';
    $periodoFin = $datos['periodo_fin'] ?? '';
    $fechaRendicion = $datos['fecha_rendicion'] ?? '';

    $resumen = obtenerResumenPorId($resumenId, $trabajadorId);
    if (!$resumen) {
        return ['ok' => false, 'error' => 'Resumen no encontrado.'];
    }
    if (!esResumenEditable($resumen)) {
        return ['ok' => false, 'error' => 'El Resumen ya está en proceso de aprobación y no se puede editar.'];
    }
    if ($periodoInicio === '' || $periodoFin === '' || $fechaRendicion === '') {
        return ['ok' => false, 'error' => 'Completa el periodo y la fecha de rendición.'];
    }
    if ($periodoFin < $periodoInicio) {
        return ['ok' => false, 'error' => 'El periodo fin no puede ser antes que el periodo inicio.'];
    }
    // El periodo del documento debe caer dentro del periodo del Resumen que lo contiene.
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
        return ['ok' => false, 'error' => 'Agrega al menos un comprobante.'];
    }

    foreach ($items as $it) {
        if ($error = validarMonto($it['monto'] ?? null)) {
            return ['ok' => false, 'error' => $error];
        }
        if (empty($it['tipo_comprobante_id']) || empty($it['serie']) || empty($it['numero_comprobante']) || empty($it['concepto']) || empty($it['fecha'])) {
            return ['ok' => false, 'error' => 'Cada comprobante necesita concepto, fecha, tipo, serie y número.'];
        }
        if ($error = validarFechaEnRango($it['fecha'], $periodoInicio, $periodoFin, 'el comprobante')) {
            return ['ok' => false, 'error' => $error];
        }
        if ($error = validarNumeroComprobante((string)$it['numero_comprobante'])) {
            return ['ok' => false, 'error' => $error];
        }
        /*
        if ($error = validarRucOpcional($it['proveedor_ruc'] ?? null)) {
            return ['ok' => false, 'error' => $error];
        }*/
    }

    $pdo = obtenerConexion();
    $pdo->beginTransaction();

    try {
        $existente = obtenerDocumentoContablePorResumen($resumenId);

        if ($existente) {
            $documentoId = (int)$existente['id'];
            $idsExistentes = obtenerDetallesIdsDocumentoContable($documentoId);
        } else {
            $numero = sgteNumeroDocumentoContable($trabajadorId);
            $documentoId = crearDocumentoContable($trabajadorId, $areaId, $numero, $periodoInicio, $periodoFin, $fechaRendicion, $resumenId);
            $idsExistentes = [];
        }

        $idsConservados = [];
        // Correlaciona la etiqueta temporal que el navegador le puso a un
        // item sin id real (_tempId, ver panelDocumento.js) con el id real
        // que recién existe acá. Se arma en el mismo momento en que se crea
        // o actualiza cada detalle, no reconstruyendo el orden después —
        // así el navegador puede emparejar por esta clave en vez de confiar
        // en que la respuesta vuelva en el mismo orden en que se mandó.
        $idsPorTempId = [];
        $item = 1;
        foreach ($items as $it) {
            $tipoComprobante = obtenerNombreTipoComprobante((int)$it['tipo_comprobante_id']) ?? '';
            if ($error = validarSerieComprobante($it['serie'], $tipoComprobante)) {
                throw new InvalidArgumentException($error);
            }
            $proveedorValido = isset($it['proveedor_valido']) ? (bool)$it['proveedor_valido'] : null;
            if ($error = validarRucComprobante($it['proveedor_ruc'] ?? null, $proveedorValido, $tipoComprobante)) {
                throw new InvalidArgumentException($error);
            }

            $rucLimpio = (!empty($it['proveedor_ruc'])) ? $it['proveedor_ruc'] : null;

            $comprobanteId = crearComprobante(
                (int)$it['tipo_comprobante_id'],
                $it['serie'],
                $it['numero_comprobante'],
                $it['fecha'],
                (float)$it['monto'],
                $rucLimpio,
                $it['proveedor_razon_social'] ?? null,
                $it['proveedor_condicion'] ?? null,
                $it['proveedor_estado'] ?? null,
                isset($it['proveedor_valido']) ? (bool)$it['proveedor_valido'] : null
            );

            $detalleId = isset($it['id']) ? (int)$it['id'] : 0;
            if ($detalleId > 0 && in_array($detalleId, $idsExistentes, true)) {
                actualizarDetalleDocumentoContable($detalleId, $item, $it['fecha'], $it['concepto'], (float)$it['monto'], $comprobanteId);
                $idsConservados[] = $detalleId;
                $idReal = $detalleId;
            } else {
                $idReal = insertarDetalleDocumentoContable($documentoId, $item, $it['fecha'],$it['concepto'], (float)$it['monto'], $comprobanteId);
            }
            if (!empty($it['_tempId'])) {
                $idsPorTempId[$it['_tempId']] = $idReal;
            }
            $item++;
        }

        foreach (array_diff($idsExistentes, $idsConservados) as $detalleId) {
            eliminarDetalleDocumentoContablePorId($detalleId);
        }
        sincronizarTotalesResumen($resumenId);
        $pdo->commit();
        return ['ok' => true, 'id' => $documentoId, 'items' => obtenerDetalleDocumentoContable($documentoId), 'idsPorTempId' => $idsPorTempId];

    } catch (Throwable $e) {
        $pdo->rollBack();
        $mensaje = $e instanceof InvalidArgumentException ? $e->getMessage() : ('No se pudo guardar: ' . $e->getMessage());
        return ['ok' => false, 'error' => $mensaje];
    }
}

// Sirve para desenlazar un docContable de un resumen
function eliminarDocumentoContableDeResumen(int $resumenId, int $trabajadorId): array
{
    $resumen = obtenerResumenPorId($resumenId, $trabajadorId);
    if (!$resumen || !esResumenEditable($resumen)) {
        return ['ok' => false, 'error' => 'Este Resumen no se puede editar.'];
    }

    $existente = obtenerDocumentoContablePorResumen($resumenId);
    if (!$existente) {
        return ['ok' => false, 'error' => 'No hay Documento Contable enlazado a este Resumen.'];
    }

    $documentoId = (int)$existente['id'];
    $pdo = obtenerConexion();
    $pdo->beginTransaction();
    try {
        $rutas = eliminarAdjuntosDocumentoContable($documentoId);
        foreach (obtenerDetallesIdsDocumentoContable($documentoId) as $detalleId) {
            eliminarDetalleDocumentoContablePorId($detalleId);
        }
        eliminarDocumentoContablePorId($documentoId);
        sincronizarTotalesResumen($resumenId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'No se pudo quitar el documento.'];
    }

    eliminarArchivosAdjuntos($rutas);
    return ['ok' => true];
}
