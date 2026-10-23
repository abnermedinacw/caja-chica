<?php

require_once __DIR__ . '/aprobaciones.php'; // esResumenEditable()

const EXTENSIONES_PERMITIDAS = ['pdf', 'png', 'jpg', 'jpeg', 'xml'];
const TAMAÑO_MAXIMO_BYTES = 5 * 1024 * 1024; //5mb
const TIPO_ADJUNTO_EVIDENCIA = 'EVIDENCIA';
const TIPO_ADJUNTO_XML = 'XML';

function validarArchivoAdjuntoItem(array $archivo, string $tipoArchivo): ?string
{
    if (!in_array($tipoArchivo, [TIPO_ADJUNTO_EVIDENCIA, TIPO_ADJUNTO_XML], true)) {
        return 'Tipo de adjunto inválido.';
    }

    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return 'Hubo un error al subir el archivo.';
    }

    if (($archivo['size'] ?? 0) > TAMAÑO_MAXIMO_BYTES) {
        return 'El archivo supera el tamaño máximo permitido de 5 MB.';
    }

    $extension = strtolower(pathinfo($archivo['name'] ?? '', PATHINFO_EXTENSION));

    if ($tipoArchivo === TIPO_ADJUNTO_EVIDENCIA) {
        if (!in_array($extension, ['pdf', 'png', 'jpg', 'jpeg'], true)) {
            return 'El sustento debe ser un archivo PDF, PNG o JPG.';
        }

        if ($extension === 'pdf') {
            $cabecera = @file_get_contents($archivo['tmp_name'], false, null, 0, 5);
            if ($cabecera !== '%PDF-') {
                return 'El archivo seleccionado no es un PDF válido.';
            }
        } elseif (@getimagesize($archivo['tmp_name']) === false) {
            return 'La imagen seleccionada no es válida.';
        }

        return null;
    }

    if ($extension !== 'xml') {
        return 'El archivo XML debe tener extensión .xml.';
    }

    return null;
}

function validarArchivoSubido(array $archivo): ?string
{
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return 'Hubo un error al subir el archivo';
    }

    if ($archivo['size'] > TAMAÑO_MAXIMO_BYTES) {
        return 'El archivo supera el tamaño maximo permitido (5MB).';
    }

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, EXTENSIONES_PERMITIDAS, true)) {
        return 'Tipo de archivo no permitido. Usa PDF, PNG o JPG.';
    }

    return null;
}

function moverArchivoSubido(array $archivo, string $carpetaRelativa): string 
{
    $carpetaCompleta = __DIR__ . '/../../storage/uploads/' . $carpetaRelativa;
    if(!is_dir($carpetaCompleta)) {
        mkdir($carpetaCompleta, 0755, true);
    }

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    $nombreFisico = uniqid('adj_', true) . '.' . $extension;

    if(!move_uploaded_file($archivo['tmp_name'], $carpetaCompleta . '/' . $nombreFisico)) {
        throw new RuntimeException('No se pudo guardar el archivo.');
    }

    return $carpetaRelativa . '/' . $nombreFisico;
}

/** Borra archivos solo después de confirmar la eliminación en la base de datos. */
function eliminarArchivosAdjuntos(array $rutas): void
{
    $raiz = realpath(__DIR__ . '/../../storage/uploads');
    if ($raiz === false) return;

    foreach ($rutas as $ruta) {
        $archivo = realpath($raiz . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ruta));
        if ($archivo === false || !is_file($archivo)) continue;
        if (strncasecmp($archivo, $raiz . DIRECTORY_SEPARATOR, strlen($raiz) + 1) === 0) {
            if (!@unlink($archivo)) {
                error_log('No se pudo eliminar el archivo adjunto: ' . $archivo);
            }
        }
    }
}

/** Reemplaza un archivo del mismo tipo sin dejar la base o el disco a medias. */
function guardarAdjuntoReemplazable(?array $anterior, string $rutaNueva, callable $eliminarAnterior, callable $insertarNuevo): void
{
    $pdo = obtenerConexion();

    try {
        $pdo->beginTransaction();
        if ($anterior) {
            $eliminarAnterior();
        }
        $insertarNuevo();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        eliminarArchivosAdjuntos([$rutaNueva]);
        throw $e;
    }

    if ($anterior) {
        eliminarArchivosAdjuntos([$anterior['ruta']]);
    }
}

// Ordena los archivos desarmandos por defecto de php a un array ordenado 
function normalizarArchivosMultiples(array $campoFiles): array
{
    if (!isset($campoFiles['name']) || !is_array($campoFiles['name'])) {
        return [];
    }

    $archivos = [];
    foreach ($campoFiles['name'] as $i => $nombre) {
        if ($nombre === '') {
            continue;
        }

        $archivos[] = [
            'name' => $campoFiles['name'][$i],
            'type' => $campoFiles['type'][$i],
            'tmp_name' => $campoFiles['tmp_name'][$i],
            'error' => $campoFiles['error'][$i],
            'size' => $campoFiles['size'][$i]
        ];
    }
    return $archivos;
}

// NOTA: estas dos funciones son de una etapa anterior del proyecto y tenían
// bugs que impedían que corrieran (nombre de función que no existe, variable
// nunca definida) — los dejo corregidos, pero de momento nada las llama
// todavía: son para adjuntar archivos "generales" al Resumen completo (no a
// un comprobante puntual), que es una pieza distinta a la que se construye
// en esta rebanada. Quedan listas para cuando toque esa parte.
function procesarAdjuntosGeneralesResumen(int $resumenId, int $usuarioId, array $campoFiles): void
{
    require_once __DIR__ . '/../Datos/adjuntos.php';
    foreach (normalizarArchivosMultiples($campoFiles) as $archivo) {
        if (validarArchivoSubido($archivo) !== null) {
            continue;
        }

        $ruta = moverArchivoSubido($archivo, 'resumen/' . $resumenId);
        insertarAdjuntoResumen($resumenId, $archivo['name'], $ruta, $usuarioId);
    }
}

function procesarAdjuntosGeneralesMovilidad(int $planillaId, int $usuarioId, array $campoFiles): void
{
    require_once __DIR__ . '/../Datos/adjuntos.php';
    foreach (normalizarArchivosMultiples($campoFiles) as $archivo) {
        if (validarArchivoSubido($archivo) !== null) {
            continue;
        }
        $ruta = moverArchivoSubido($archivo, 'movilidad/' . $planillaId);
        insertarAdjuntoMovilidad($planillaId, null, $archivo['name'], $ruta, $usuarioId);
    }
}

// Documento Contable: un adjunto por comprobante 
function subirAdjuntoDocumentoContable(int $trabajadorId, int $usuarioId, int $documentoId, int $detalleId, array $archivo, string $tipoArchivo): array
{
    require_once __DIR__ . '/../Datos/documentoContable.php';
    require_once __DIR__ . '/../Datos/cajaChica.php';
    require_once __DIR__ . '/../Datos/adjuntos.php';

    $documento = obtenerDocumentoContablePorId($documentoId, $trabajadorId);
    if (!$documento) {
        return ['ok' => false, 'error' => 'Documento no encontrado.'];
    }
    $resumen = obtenerResumenPorId((int)$documento['resumen_id'], $trabajadorId);
    if (!$resumen || !esResumenEditable($resumen)) {
        return ['ok' => false, 'error' => 'Este Resumen ya no se puede editar.'];
    }
    if (!in_array($detalleId, obtenerDetallesIdsDocumentoContable($documentoId), true)) {
        return ['ok' => false, 'error' => 'Ese comprobante no pertenece a este documento.'];
    }
    if ($error = validarArchivoAdjuntoItem($archivo, $tipoArchivo)) {
        return ['ok' => false, 'error' => $error];
    }

    $anterior = obtenerAdjuntoDocumentoContablePorDetalleYTipo($documentoId, $detalleId, $tipoArchivo);
    $ruta = moverArchivoSubido($archivo, 'documento_contable/' . $documentoId);

    try {
        guardarAdjuntoReemplazable(
            $anterior,
            $ruta,
            fn() => eliminarAdjuntoDocumentoContablePorId((int)$anterior['id']),
            fn() => insertarAdjuntoDocumentoContable($documentoId, $detalleId, $archivo['name'], $ruta, $usuarioId, $tipoArchivo)
        );
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'No se pudo guardar el archivo adjunto.'];
    }

    return ['ok' => true];
}

function quitarAdjuntoDocumentoContable(int $trabajadorId, int $documentoId, int $adjuntoId): array
{
    require_once __DIR__ . '/../Datos/documentoContable.php';
    require_once __DIR__ . '/../Datos/cajaChica.php';
    require_once __DIR__ . '/../Datos/adjuntos.php';

    $documento = obtenerDocumentoContablePorId($documentoId, $trabajadorId);
    if (!$documento) {
        return ['ok' => false, 'error' => 'Documento no encontrado.'];
    }
    $resumen = obtenerResumenPorId((int)$documento['resumen_id'], $trabajadorId);
    if (!$resumen || !esResumenEditable($resumen)) {
        return ['ok' => false, 'error' => 'Este Resumen ya no se puede editar.'];
    }

    $adjunto = obtenerAdjuntoDocumentoContablePorId($adjuntoId, $documentoId);
    if (!$adjunto) {
        return ['ok' => false, 'error' => 'Archivo no encontrado.'];
    }

    eliminarAdjuntoDocumentoContablePorId($adjuntoId);
    eliminarArchivosAdjuntos([$adjunto['ruta']]);
    return ['ok' => true];
}

// Documento No Contable
function subirAdjuntoDocumentoNoContable(int $trabajadorId, int $usuarioId, int $documentoId, int $detalleId, array $archivo, string $tipoArchivo): array
{
    require_once __DIR__ . '/../Datos/documentoNoContable.php';
    require_once __DIR__ . '/../Datos/cajaChica.php';
    require_once __DIR__ . '/../Datos/adjuntos.php';

    $documento = obtenerDocumentoNoContablePorId($documentoId, $trabajadorId);
    if (!$documento) {
        return ['ok' => false, 'error' => 'Documento no encontrado.'];
    }

    $resumen = obtenerResumenPorId((int)$documento['resumen_id'], $trabajadorId);
    if (!$resumen || !esResumenEditable($resumen)) {
        return ['ok' => false, 'error' => 'Este Resumen ya no se puede editar.'];
    }

    if (!in_array($detalleId, obtenerDetallesIdsDocumentoNoContable($documentoId), true)) {
        return ['ok' => false, 'error' => 'Ese comprobante no pertenece a este documento.'];
    }

    if ($tipoArchivo !== TIPO_ADJUNTO_EVIDENCIA) {
        return ['ok' => false, 'error' => 'La planilla de movilidad solo admite evidencias PDF o imagen.'];
    }

    if ($error = validarArchivoAdjuntoItem($archivo, $tipoArchivo)) {
        return ['ok' => false, 'error' => $error];
    }

    $anterior = obtenerAdjuntoDocumentoNoContablePorDetalleYTipo($documentoId, $detalleId, $tipoArchivo);
    $ruta = moverArchivoSubido($archivo, 'documento_no_contable/' . $documentoId);

    try {
        guardarAdjuntoReemplazable(
            $anterior,
            $ruta,
            fn() => eliminarAdjuntoDocumentoNoContablePorId((int)$anterior['id']),
            fn() => insertarAdjuntoDocumentoNoContable($documentoId, $detalleId, $archivo['name'], $ruta, $usuarioId, $tipoArchivo)
        );
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'No se pudo guardar el archivo adjunto.'];
    }

    return['ok' => true];
}

function quitarAdjuntoDocumentoNoContable(int $trabajadorId, int $documentoId, int $adjuntoId): array
{
    require_once __DIR__ . '/../Datos/documentoNoContable.php';
    require_once __DIR__ . '/../Datos/cajaChica.php';
    require_once __DIR__ . '/../Datos/adjuntos.php';
    
    $documento = obtenerDocumentoNoContablePorId($documentoId, $trabajadorId);
    if (!$documento) {
        return ['ok' => false, 'error' => 'Documento no encontrado.'];
    }

    $resumen = obtenerResumenPorId((int)$documento['resumen_id'], $trabajadorId);
    if (!$resumen || !esResumenEditable($resumen)) {
        return ['ok' => false, 'error' => 'Este Resumen ya no se puede editar.'];
    }

    $adjunto = obtenerAdjuntoDocumentoNoContablePorId($adjuntoId, $documentoId);
    if (!$adjunto) {
        return ['ok' => false, 'error' => 'Archivo no encontrado.'];
    }

    eliminarAdjuntoDocumentoNoContablePorId($adjuntoId);
    eliminarArchivosAdjuntos([$adjunto['ruta']]);
    return['ok' => true];
}

// Planilla de movilidad
function subirAdjuntoPlanilla(int $trabajadorId, int $usuarioId, int $planillaId, int $detalleId, array $archivo, string $tipoArchivo): array
{
    require_once __DIR__ . '/../Datos/planillaMovilidad.php';
    require_once __DIR__ . '/../Datos/cajaChica.php';
    require_once __DIR__ . '/../Datos/adjuntos.php';

    $planilla = obtenerPlanillaPorId($planillaId, $trabajadorId);
    if (!$planilla) {
        return['ok' => false, 'error' => 'Planilla no encontrada.'];
    }

    $resumen = obtenerResumenPorId((int)$planilla['resumen_id'], $trabajadorId);
    if (!$resumen || !esResumenEditable($resumen)) {
        return['ok' => false, 'error' => 'Este resumen ya no se puede editar.'];
    }

    if(!in_array($detalleId, obtenerDetallesIdsPlanilla($planillaId), true)) {
        return ['ok' => false, 'error' => 'Ese viaje no pertenece a esta planilla.'];
    }

    if($error = validarArchivoAdjuntoItem($archivo, $tipoArchivo)) {
        return ['ok' => false, 'error' => $error];
    }

    $anterior = obtenerAdjuntoMovilidadPorDetalleYTipo($planillaId, $detalleId, $tipoArchivo);
    $ruta = moverArchivoSubido($archivo, 'movilidad/' . $planillaId);

    try {
        guardarAdjuntoReemplazable(
            $anterior,
            $ruta,
            fn() => eliminarAdjuntoMovilidadPorId((int)$anterior['id']),
            fn() => insertarAdjuntoMovilidad($planillaId, $detalleId, $archivo['name'], $ruta, $usuarioId, $tipoArchivo)
        );
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'No se pudo guardar el archivo adjunto.'];
    }

    return ['ok' => true];
}

function quitarAdjuntoPlanilla(int $trabajadorId, int $planillaId, int $adjuntoId): array
{
    require_once __DIR__ . '/../Datos/planillaMovilidad.php';
    require_once __DIR__ . '/../Datos/cajaChica.php';
    require_once __DIR__ . '/../Datos/adjuntos.php';

    $planilla = obtenerPlanillaPorId($planillaId, $trabajadorId);
    if (!$planilla) {
        return['ok' => false, 'error' => 'Planilla no encontrada.'];
    }

    $resumen = obtenerResumenPorId((int)$planilla['resumen_id'], $trabajadorId);
    if (!$resumen || !esResumenEditable($resumen)) {
        return['ok' => false, 'error' => 'Este resumen ya no se puede editar.'];
    }

    $adjunto = obtenerAdjuntoMovilidadPorId($adjuntoId, $planillaId);
    if (!$adjunto) {
        return ['ok' => false, 'error' => 'Archivo no encontrado.'];
    }

    eliminarAdjuntoMovilidadPorId($adjuntoId);
    eliminarArchivosAdjuntos([$adjunto['ruta']]);
    return ['ok' => true];
}
