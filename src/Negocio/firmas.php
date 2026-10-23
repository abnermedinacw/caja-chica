<?php
require_once __DIR__ . '/../Datos/firmas.php';

const FIRMA_TAMANIO_MAXIMO = 2*1024*1024;

function guardarFirmaPersonal(int $usuarioId, array $archivo): array
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [
            'ok' => false,
            'error' => 'Selecciona una imagen válida'
        ];
    }

    if (($archivo['size'] ?? 0) > FIRMA_TAMANIO_MAXIMO) {
        return [
            'ok' => false,
            'error' => 'La firma no debe superar los 2MB.'
        ];
    }

    $temporal = $archivo['tmp_name'] ?? '';

    if ($temporal === '' || !is_uploaded_file($temporal)) {
        return [
            'ok' => false,
            'error' => 'El archivo recibido no es válido'
        ];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($temporal);

    $extensiones = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
    ];

    if (!isset($extensiones[$mime])) return ['ok' => false, 'error' => 'La firma debe ser una imagen PNG o JPG'];

    $firmaAnterior = obtenerFirmaPersonal($usuarioId);

    $carpetaRelativa = 'firmas/personales/usuario_' . $usuarioId;
    $carpetaAbsoluta = __DIR__ . '/../../storage/uploads/' . $carpetaRelativa;

    if (!is_dir($carpetaAbsoluta) && !mkdir($carpetaAbsoluta, 0755, true) && !is_dir($carpetaAbsoluta)) {
        return [
            'ok' => false,
            'error' => 'No se pudo crear la carpeta para la firma.'
        ];
    }

    try {
        $nombre = 'firma_' . bin2hex(random_bytes(8)) . '.' . $extensiones[$mime];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => 'No se pudo generar el nombre del archivo.'
        ];
    }

    $rutaRelativa = $carpetaRelativa . '/' . $nombre;
    $rutaAbsoluta = $carpetaAbsoluta . DIRECTORY_SEPARATOR . $nombre;

    if(!move_uploaded_file($temporal, $rutaAbsoluta)) {
        return [
            'ok' => false,
            'error' => 'No se pudo guardar la firma.'
        ];
    }

    try {
        guardarRutaFirmaPersonal($usuarioId, $rutaRelativa);
    } catch (Throwable $e) {
        if (is_file($rutaAbsoluta)) {
            unlink($rutaAbsoluta);
        }

        return [
            'ok' => false, 
            'error' => 'No se pudo registrar la firma en la base de datos.'
        ];
    }

    if (!empty($firmaAnterior['ruta_imagen'])) {
        $rutaAnterior = obtenerRutaAbsolutaFirma($firmaAnterior['ruta_imagen']);

        if ($rutaAnterior !== null && $rutaAnterior !== $rutaAbsoluta && is_file($rutaAnterior)) {
            unlink($rutaAnterior);
        }
    }

    return ['ok' => true];
}

// Sirve para obtener la ruta absoluta de un archivo de firma a partir de su ruta relativa.
function obtenerRutaAbsolutaFirma(?string $rutaRelativa): ?string
{
    if (!$rutaRelativa) return null;

    $raiz = realpath(__DIR__ . '/../../storage/uploads');

    if ($raiz === false) return null;

    $rutaNormalizada = str_replace(['/', '\\'],DIRECTORY_SEPARATOR, $rutaRelativa);
    $archivo = realpath($raiz . DIRECTORY_SEPARATOR . $rutaNormalizada);

    if ($archivo === false || !is_file($archivo)) return null;

    $prefijoPermitido = $raiz . DIRECTORY_SEPARATOR;
    // Por seguridad, aseguramos que el archivo esté dentro de la carpeta de uploads
    if (strncasecmp($archivo, $prefijoPermitido, strlen($prefijoPermitido)) !== 0) return null;
    return $archivo;
}

function prepararFirmasPdf(int $resumenId, array $resumen): array
{
    $casillas = [
        'RESPONSABLE' => [
                'etiqueta' => 'RESPONSABLE',
                'nombre' => '',
                'fecha' => '',
                'imagen' => null,
                'automatico' => false,
        ],
        'CONTABILIDAD_1' => [
            'etiqueta' => 'RECIBIDO POR CONTABILIDAD',
            'nombre' => '',
            'fecha' => '',
            'imagen' => null,
            'automatico' => false,
        ],
        'JEFATURA' => [
            'etiqueta' => 'APROBADO POR JEFATURA',
            'nombre' => '',
            'fecha' => '',
            'imagen' => null,
            'automatico' => false,
        ],
        'GERENCIA' => [
            'etiqueta' => 'REVISADO POR GERENCIA',
            'nombre' => '',
            'fecha' => '',
            'imagen' => null,
            'automatico' => false,
        ],
        'CONTABILIDAD_2' => [
            'etiqueta' => 'APROBADO POR CONTABILIDAD',
            'nombre' => '',
            'fecha' => '',
            'imagen' => null,
            'automatico' => false,
        ],
    ];

    $responsable = obtenerFirmanteSolicitantePdf($resumenId);
    if ($responsable) {
        $firma = obtenerFirmaPersonal((int)$responsable['usuario_id']);

        $casillas['RESPONSABLE']['nombre'] = $responsable['nombre'];

        $casillas['RESPONSABLE']['fecha'] = formatearFecha($responsable['fecha']);

        $casillas['RESPONSABLE']['imagen'] = obtenerRutaAbsolutaFirma($firma['ruta_imagen'] ?? null);
    }

    foreach (obtenerFirmanteNivelesPdf($resumenId) as $evento) {
        $codigo = $evento['codigo'];

        if (!isset($casillas[$codigo])) continue;

        $firma = null;
        $usuarioId = (int)$evento['usuario_id'];
        $empresaId = (int)$resumen['empresa_id'];

        if ($codigo === 'JEFATURA') $firma = obtenerFirmaPersonal($usuarioId);
        elseif ($codigo === 'GERENCIA') $firma = obtenerFirmaGerencia($usuarioId, $empresaId);
        elseif (in_array($codigo, ['CONTABILIDAD_1', 'CONTABILIDAD_2'], true)) $firma = obtenerFirmaContabilidad($empresaId);

        $casillas[$codigo]['nombre'] = $evento['nombre'];
        $casillas[$codigo]['fecha'] = formatearFecha($evento['fecha']);
        $casillas[$codigo]['imagen'] = obtenerRutaAbsolutaFirma($firma['ruta_imagen'] ?? null);
        $casillas[$codigo]['automatico'] = $evento['evento'] === 'AUTO_VALIDADO';
    }

    return array_values($casillas);
}