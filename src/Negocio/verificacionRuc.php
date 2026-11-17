<?php
require_once __DIR__ . '/../../config/ruc.php';

function consultarRuc(string $ruc): array
{
    if (!preg_match('/^\d{11}$/', $ruc)) {
        return ['ok' => false, 'error' => 'El RUC debe tener 11 dígitos.'];
    }

    // RUC 10 (y otros de persona natural: 15, 17): prioriza chequea.pe porque
    // los demás proveedores gratuitos suelen restringir RUC 10.
    // RUC 20 u otros: mantiene OpenRUC -> apiperu.dev -> chequea.pe.
    $esPersonaNatural = in_array(substr($ruc, 0, 2), ['10', '15', '17'], true);
    $proveedores = $esPersonaNatural
        ? [
            'chequea' => fn() => _consultarChequea($ruc),
            'openruc' => fn() => _consultarOpenRuc($ruc),
            'apiperu' => fn() => _consultarApiperu($ruc),
        ]
        : [
            'openruc' => fn() => _consultarOpenRuc($ruc),
            'apiperu' => fn() => _consultarApiperu($ruc),
            'chequea' => fn() => _consultarChequea($ruc),
        ];

    foreach ($proveedores as $nombre => $fn) {
        $resultado = $fn();
        if ($resultado['ok'] === true) {
            error_log("RUC $ruc verificado por $nombre");
            return $resultado;
        }
        error_log("Fallo RUC $ruc en $nombre: " . ($resultado['error'] ?? 'desconocido'));
    }

    return ['ok' => false, 'error' => 'No se pudo verificar el RUC. Todos los servicios no respondieron.'];
}

function _consultarOpenRuc(string $ruc): array
{
    $url = 'https://openruc.com/api/ruc/' . urlencode($ruc);
    $res = _httpGetJson($url, [], 6, 'OpenRUC');
    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error']];
    }

    $codigoHttp = $res['http'];
    $datos = $res['datos'];

    // OpenRUC: 200 OK con {razon_social, condicion, estado}
    // 404 = no existe, 400 = inválido, 503 = servicio pausado
    if ($codigoHttp === 404) {
        return ['ok' => false, 'error' => $datos['message'] ?? 'Ese RUC no existe en SUNAT.'];
    }
    if ($codigoHttp === 400) {
        return ['ok' => false, 'error' => $datos['message'] ?? 'RUC inválido'];
    }
    if ($codigoHttp !== 200 || !is_array($datos) || empty($datos['razon_social'])) {
        return ['ok' => false, 'error' => $datos['message'] ?? "OpenRUC respondió HTTP $codigoHttp."];
    }

    return _normalizarResultado($datos['razon_social'], $datos['condicion'] ?? null, $datos['estado'] ?? null);
}

function _consultarApiperu(string $ruc): array
{
    $config = require __DIR__ . '/../../config/ruc.php';
    $token = $config['apiperu']['token'] ?? '';
    if ($token === '') return ['ok' => false, 'error' => 'Token apiperu no configurado.'];

    $url = $config['apiperu']['url'] . '/' . urlencode($ruc);
    $res = _httpGetJson($url, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ], 8, 'apiperu');
    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error']];
    }

    $codigoHttp = $res['http'];
    $datos = $res['datos'];

    // apiperu.dev: { success: true, data: { nombre_o_razon_social, condicion, estado, ... } }
    // Error: { success: false, message: "..." }
    if ($codigoHttp !== 200 || !is_array($datos) || empty($datos['success']) || empty($datos['data']['nombre_o_razon_social'])) {
        return ['ok' => false, 'error' => $datos['message'] ?? "apiperu respondió HTTP $codigoHttp."];
    }

    $d = $datos['data'];
    return _normalizarResultado($d['nombre_o_razon_social'], $d['condicion'] ?? null, $d['estado'] ?? null);
}

/**
 * Consulta datos de un RUC (incluyendo RUC 10, 15, 17 y 20) usando la API v1 de chequea.pe.
 */
function _consultarChequea(string $ruc): array
{
    $config = require __DIR__ . '/../../config/ruc.php';
    $token = preg_replace('/^Bearer\s+/i', '', trim((string)($config['chequea']['token'] ?? '')));
    $token = preg_replace('/\s+/', '', (string)$token);
    if ($token === '') return ['ok' => false, 'error' => 'Token chequea.pe no configurado.'];

    $baseUrl = rtrim((string)($config['chequea']['url'] ?? 'https://api.chequea.pe/api/v1/ruc'), '/');
    $url = $baseUrl . '/' . urlencode($ruc);
    $res = _httpGetJson($url, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ], 8, 'chequea.pe');
    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error']];
    }

    $codigoHttp = $res['http'];
    $datos = $res['datos'];

    // chequea.pe: 200 OK -> { ruc, razonSocial, estado, condicion, ... }
    // Errores (RFC 9457 Problem Details): { type, title, status, detail, instance }
    if ($codigoHttp === 404) {
        return ['ok' => false, 'error' => $datos['detail'] ?? 'Ese RUC no existe en SUNAT.'];
    }
    if ($codigoHttp === 400) {
        return ['ok' => false, 'error' => $datos['detail'] ?? 'RUC inválido.'];
    }
    if ($codigoHttp !== 200 || !is_array($datos) || empty($datos['razonSocial'])) {
        return ['ok' => false, 'error' => $datos['detail'] ?? ($datos['title'] ?? ($datos['message'] ?? "chequea.pe respondió HTTP $codigoHttp."))];
    }

    return _normalizarResultado(
        trim((string)$datos['razonSocial']),
        isset($datos['condicion']) ? trim((string)$datos['condicion']) : null,
        isset($datos['estado']) ? trim((string)$datos['estado']) : null
    );
}

/**
 * Ejecuta una petición HTTP GET con opciones compatibles con cPanel/Cloudflare (IPv4 + User-Agent).
 */
function _httpGetJson(string $url, array $headers, int $timeout, string $proveedor): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_USERAGENT => 'CajaChica/1.0',
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $respuesta = curl_exec($ch);
    $codigoHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);
    curl_close($ch);

    if ($errorCurl) {
        return ['ok' => false, 'error' => "Error de red con $proveedor: $errorCurl"];
    }

    return [
        'ok' => true,
        'http' => $codigoHttp,
        'datos' => json_decode((string)$respuesta, true),
    ];
}

function _normalizarResultado(string $razonSocial, ?string $condicion, ?string $estado): array
{
    return [
        'ok'         => true,
        'razon_social' => $razonSocial,
        'condicion'  => $condicion,
        'estado'     => $estado,
        'valido'     => ($estado ?? '') === 'ACTIVO' && ($condicion ?? '') === 'HABIDO',
    ];
}