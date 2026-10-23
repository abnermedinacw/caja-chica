<?php
require_once __DIR__ . '/../Datos/documentoContable.php';
require_once __DIR__ . '/../Datos/documentoNoContable.php';
require_once __DIR__ . '/../Datos/planillaMovilidad.php';

/** Carga una sola representación del detalle para lectura, edición y aprobación. */
function cargarDetalleResumen(int $resumenId): array
{
    $documentoContable = obtenerDocumentoContablePorResumen($resumenId);
    $itemsDocumentoContable = [];
    $adjuntosSueltosContable = [];
    if ($documentoContable) {
        $documentoContable['total'] = obtenerTotalDocumentoContable((int)$documentoContable['id']);
        $itemsDocumentoContable = obtenerDetalleDocumentoContable((int)$documentoContable['id']);
        $adjuntosSueltosContable = agregarAdjuntosAItems($itemsDocumentoContable, obtenerAdjuntosDocumentoContable((int)$documentoContable['id']));
    }

    $documentoNoContable = obtenerDocumentoNoContablePorResumen($resumenId);
    $itemsDocumentoNoContable = [];
    $adjuntosSueltosNoContable = [];
    if ($documentoNoContable) {
        $documentoNoContable['total'] = obtenerTotalDocumentoNoContable((int)$documentoNoContable['id']);
        $itemsDocumentoNoContable = obtenerDetalleDocumentoNoContable((int)$documentoNoContable['id']);
        $adjuntosSueltosNoContable = agregarAdjuntosAItems($itemsDocumentoNoContable, obtenerAdjuntosDocumentoNoContable((int)$documentoNoContable['id']));
    }

    $planilla = obtenerPlanillaPorResumen($resumenId);
    $itemsPlanilla = [];
    $adjuntosSueltosPlanilla = [];
    if ($planilla) {
        $itemsPlanilla = obtenerDetallePlanilla((int)$planilla['id']);
        $adjuntosSueltosPlanilla = agregarAdjuntosAItems($itemsPlanilla, obtenerAdjuntosPlanilla((int)$planilla['id']));
    }

    return compact(
        'documentoContable', 'itemsDocumentoContable',
        'documentoNoContable', 'itemsDocumentoNoContable',
        'planilla', 'itemsPlanilla',
        'adjuntosSueltosContable', 'adjuntosSueltosNoContable', 'adjuntosSueltosPlanilla'
    );
}

/** Devuelve los adjuntos cuyo ítem fue eliminado o ya no existe. */
function agregarAdjuntosAItems(array &$items, array $adjuntos): array
{
    $porDetalle = [];
    foreach ($adjuntos as $adjunto) {
        $porDetalle[(int)$adjunto['detalle_id']][] = $adjunto;
    }
    foreach ($items as &$item) {
        $id = (int)$item['id'];
        $item['adjuntos'] = $porDetalle[$id] ?? [];
        unset($porDetalle[$id]);
    }
    unset($item);

    return $porDetalle ? array_merge(...array_values($porDetalle)) : [];
}
