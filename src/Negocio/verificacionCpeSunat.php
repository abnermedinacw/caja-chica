<?php
// Servicio de validación de Comprobantes de Pago Electrónicos (CPE) contra la API oficial de SUNAT.
// Selecciona las credenciales OAuth2 según el RUC de la empresa dueña del Resumen.

function _obtenerCredencialesSunat(string $rucEmpresa): ?array
{
    $rutaConfig = __DIR__ . '/../../config/sunat.php';
    if (!is_file($rutaConfig)) {
        return null;
    }
    $config = require $rutaConfig;
    $cred = $config['empresas'][$rucEmpresa] ?? null;
    if (!is_array($cred) || empty($cred['client_id']) || empty($cred['client_secret'])) {
        return null;
    }
    return $cred;
}

function _httpPostSunat(string $url, string $body, array $headers): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_USERAGENT      => 'T-RINDE/1.0',
    ]);
    $respuesta = curl_exec($ch);
    $codigoHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false) {
        return ['ok' => false, 'http' => 0, 'error' => 'Error de conexión con SUNAT: ' . $errorCurl];
    }

    return [
        'ok'    => true,
        'http'  => $codigoHttp,
        'datos' => json_decode($respuesta, true),
        'raw'   => $respuesta,
    ];
}

function _obtenerTokenSunat(string $clientId, string $clientSecret, bool $forzarRenovacion = false): array
{
    $archivoCache = sys_get_temp_dir() . '/sunat_cpe_token_' . md5($clientId) . '.json';

    // Reutilizamos el token en caché mientras le queden más de 60 segundos de vigencia.
    if (!$forzarRenovacion && is_file($archivoCache)) {
        $cache = json_decode((string)@file_get_contents($archivoCache), true);
        if (!empty($cache['token']) && ($cache['expira'] ?? 0) > time() + 60) {
            return ['ok' => true, 'token' => $cache['token']];
        }
    }

    $url = "https://api-seguridad.sunat.gob.pe/v1/clientesextranet/{$clientId}/oauth2/token/";
    $body = http_build_query([
        'grant_type'    => 'client_credentials',
        'scope'         => 'https://api.sunat.gob.pe/v1/contribuyente/contribuyentes',
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
    ]);

    $res = _httpPostSunat($url, $body, ['Content-Type: application/x-www-form-urlencoded']);
    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error']];
    }

    $datos = $res['datos'];
    if (empty($datos['access_token'])) {
        $detalle = $datos['error_description'] ?? ($datos['error'] ?? ('HTTP ' . $res['http']));
        return ['ok' => false, 'error' => 'No se pudo autenticar con SUNAT: ' . $detalle];
    }

    @file_put_contents($archivoCache, json_encode([
        'token'  => $datos['access_token'],
        'expira' => time() + (int)($datos['expires_in'] ?? 3600),
    ]));

    return ['ok' => true, 'token' => $datos['access_token']];
}

function consultarComprobanteSunat(
    string $rucEmpresa,
    string $rucEmisor,
    string $codComp,
    string $serie,
    string $numero,
    string $fechaIso,
    float $monto
): array {
    $rucEmpresa = preg_replace('/\D/', '', $rucEmpresa) ?? '';
    $cred = _obtenerCredencialesSunat($rucEmpresa);
    if (!$cred) {
        return ['ok' => false, 'error' => 'No hay credenciales de API SUNAT configuradas para la empresa de este Resumen.'];
    }

    $timestamp = strtotime($fechaIso);
    if ($timestamp === false) {
        return ['ok' => false, 'error' => 'La fecha de emisión no tiene un formato válido.'];
    }

    // Normalización exigida por la API CPE de SUNAT:
    // serie en mayúsculas, número sin ceros a la izquierda, fecha dd/mm/yyyy y monto con 2 decimales.
    $payload = json_encode([
        'numRuc'       => trim($rucEmisor),
        'codComp'      => strtoupper(trim($codComp)),
        'numeroSerie'  => strtoupper(trim($serie)),
        'numero'       => ltrim(trim($numero), '0') ?: '0',
        'fechaEmision' => date('d/m/Y', $timestamp),
        'monto'        => number_format($monto, 2, '.', ''),
    ]);

    $url = "https://api.sunat.gob.pe/v1/contribuyente/contribuyentes/{$rucEmpresa}/validarcomprobante";

    // Reintentamos una sola vez si el token en caché fue revocado (HTTP 401).
    $respuestaApi = null;
    for ($intento = 0; $intento < 2; $intento++) {
        $tok = _obtenerTokenSunat($cred['client_id'], $cred['client_secret'], $intento === 1);
        if (!$tok['ok']) {
            return ['ok' => false, 'error' => $tok['error']];
        }

        $respuestaApi = _httpPostSunat($url, (string)$payload, [
            'Authorization: Bearer ' . $tok['token'],
            'Content-Type: application/json',
        ]);

        if (!$respuestaApi['ok'] || $respuestaApi['http'] !== 401) {
            break;
        }
    }

    if (!$respuestaApi || !$respuestaApi['ok']) {
        return ['ok' => false, 'error' => $respuestaApi['error'] ?? 'No se obtuvo respuesta de SUNAT.'];
    }

    $datos = $respuestaApi['datos'];
    if (!is_array($datos) || empty($datos['success']) || !isset($datos['data']['estadoCp'])) {
        $msg = is_array($datos) ? ($datos['message'] ?? $datos['msg'] ?? '') : '';
        return [
            'ok'    => false,
            'error' => $msg !== '' ? "SUNAT respondió: $msg" : ('Respuesta inesperada de SUNAT (HTTP ' . $respuestaApi['http'] . ').'),
        ];
    }

    // Catálogos oficiales de respuesta de la API CPE de SUNAT.
    $mapaEstadoCp = [
        '0' => 'NO EXISTE',
        '1' => 'ACEPTADO',
        '2' => 'ANULADO',
        '3' => 'AUTORIZADO',
        '4' => 'NO AUTORIZADO',
    ];
    $mapaEstadoRuc = [
        '00' => 'ACTIVO',
        '01' => 'BAJA PROVISIONAL',
        '02' => 'BAJA PROV. POR OFICIO',
        '03' => 'SUSPENSIÓN TEMPORAL',
        '10' => 'BAJA DEFINITIVA',
        '11' => 'BAJA DE OFICIO',
        '22' => 'INHABILITADO-VENT.UNICA',
    ];
    $mapaCondDomiRuc = [
        '00' => 'HABIDO',
        '09' => 'PENDIENTE',
        '11' => 'POR VERIFICAR',
        '12' => 'NO HABIDO',
        '20' => 'NO HALLADO',
    ];

    $d = $datos['data'];
    $codCp = (string)($d['estadoCp'] ?? '');
    $codRuc = (string)($d['estadoRuc'] ?? '');
    $codCond = (string)($d['condDomiRuc'] ?? '');

    $estadoCp = $mapaEstadoCp[$codCp] ?? ($codCp !== '' ? "CÓDIGO $codCp" : 'DESCONOCIDO');
    $estadoRuc = $mapaEstadoRuc[$codRuc] ?? ($codRuc !== '' ? "CÓDIGO $codRuc" : '-');
    $condicionRuc = $mapaCondDomiRuc[$codCond] ?? ($codCond !== '' ? "CÓDIGO $codCond" : '-');

    $observaciones = [];
    if (!empty($d['observaciones']) && is_array($d['observaciones'])) {
        $observaciones = array_values(array_filter(array_map('strval', $d['observaciones'])));
    }

    // Validación estricta acordada: solo válido con ACEPTADO (1) + ACTIVO (00) + HABIDO (00).
    $valido = ($codCp === '1' && $codRuc === '00' && $codCond === '00');

    return [
        'ok'                     => true,
        'valido'                 => $valido,
        'cpe_estado_sunat'       => $estadoCp,
        'cpe_estado_ruc'         => $estadoRuc,
        'cpe_condicion_ruc'      => $condicionRuc,
        'observaciones'          => $observaciones,
        'fecha_verificacion_cpe' => date('Y-m-d H:i:s'),
    ];
}
