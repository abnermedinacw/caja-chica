<?php
require_once __DIR__ . '/../../config/ruc.php';

function consultarRuc(string $ruc): array
{
    if (!preg_match('/^\d{11}$/', $ruc)) {
        return ['ok' => false, 'error' => 'El RUC debe tener 11 dígitos.'];
    }

    // Cadena de fallback: OpenRUC -> apiperu.dev -> apis.net.pe
    $proveedores = [
        'openruc'   => fn() => _consultarOpenRuc($ruc),
        'apiperu'   => fn() => _consultarApiperu($ruc),
        'apisnetpe' => fn() => _consultarApisNetPe($ruc),
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

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
    ]);
    $respuesta = curl_exec($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);
    curl_close($ch);

    if ($errorCurl) {
        return ['ok' => false, 'error' => 'No se pudo conectar con OpenRUC.'];
    }

    $datos = json_decode($respuesta, true);

    // OpenRUC: 200 OK con {razon_social, condicion, estado}
    // 404 = no existe, 400 = inválido, 503 = servicio pausado
    if ($codigoHttp === 404) {
        return ['ok' => false, 'error' => $datos['message'] ?? 'Ese RUC no existe en SUNAT.'];
    }
    if ($codigoHttp === 400) {
        return ['ok' => false, 'error' => $datos['message'] ?? 'RUC inválido'];
    }
    if ($codigoHttp !== 200 || !is_array($datos) || empty($datos['razon_social'])) {
        return ['ok' => false, 'error' => $datos['message'] ?? 'OpenRUC no respondió bien.'];
    }

    return _normalizarResultado($datos['razon_social'], $datos['condicion'] ?? null, $datos['estado'] ?? null);
}

function _consultarApiperu(string $ruc): array
{
    $config = require __DIR__ . '/../../config/ruc.php';
    $token = $config['apiperu']['token'] ?? '';
    if ($token === '') return ['ok' => false, 'error' => 'Token apiperu no configurado.'];

    $url = $config['apiperu']['url'] . '/' . urlencode($ruc);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
    ]);
    $respuesta = curl_exec($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);
    curl_close($ch);

    if ($errorCurl) return ['ok' => false, 'error' => 'Error de red con apiperu.'];

    $datos = json_decode($respuesta, true);

    // apiperu.dev: { success: true, data: { nombre_o_razon_social, condicion, estado, ... } }
    // Error: { success: false, message: "..." }
    if ($codigoHttp !== 200 || !is_array($datos) || empty($datos['success']) || empty($datos['data']['nombre_o_razon_social'])) {
        return ['ok' => false, 'error' => $datos['message'] ?? 'apiperu no respondió bien.'];
    }

    $d = $datos['data'];
    return _normalizarResultado($d['nombre_o_razon_social'], $d['condicion'] ?? null, $d['estado'] ?? null);
}

function _consultarApisNetPe(string $ruc): array
{
    $config = require __DIR__ . '/../../config/ruc.php';
    $token = $config['apisnetpe']['token'] ?? '';
    if ($token === '') return ['ok' => false, 'error' => 'Token apis.net.pe no configurado.'];

    $url = $config['apisnetpe']['url'] . '/' . urlencode($ruc) . '?token=' . urlencode($token);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
    ]);
    $respuesta = curl_exec($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);
    curl_close($ch);

    if ($errorCurl) return ['ok' => false, 'error' => 'Error de red con apis.net.pe.'];

    $datos = json_decode($respuesta, true);

    // apis.net.pe v2: { success: true, razonSocial, condicion, estado, ... }
    // Error: { success: false, message: "..." } o { error: "..." }
    if ($codigoHttp !== 200 || !is_array($datos) || empty($datos['success']) || empty($datos['razonSocial'])) {
        return ['ok' => false, 'error' => $datos['message'] ?? ($datos['error'] ?? 'apis.net.pe no respondió bien.')];
    }

    return _normalizarResultado($datos['razonSocial'], $datos['condicion'] ?? null, $datos['estado'] ?? null);
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