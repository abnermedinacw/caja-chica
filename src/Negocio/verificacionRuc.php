<?php

function consultarRuc(string $ruc): array
{
    if (!preg_match('/^\d{11}$/', $ruc)) {
        return ['ok' => false, 'error' => 'El RUC debe tener 11 dígitos.'];
    }

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
        return ['ok' => false, 'error' => 'No se pudo conectar con el servicio de RUC. Intenta de nuevo.'];
    }

    $datos = json_decode($respuesta, true);
    
    if ($codigoHttp === 404) {
        return ['ok' => false, 'error' => $datos['message'] ?? 'Ese RUC no existe en SUNAT.'];
    }
    if ($codigoHttp === 400) {
        return ['ok' => false, 'error' => $datos['message'] ?? 'RUC inválido'];
    }
    if ($codigoHttp !== 200 || !is_array($datos) || empty($datos['razon_social'])) {
        return ['ok' => false, 'error' => $datos['message'] ?? 'El servicio de verificación de RUC no respondió bien.'];
    }

    return [
        'ok' => true,
        'razon_social' => $datos['razon_social'],
        'condicion' => $datos['condicion'] ?? null,
        'estado' => $datos['estado'] ?? null,
        'valido' => ($datos['estado'] ?? '') === 'ACTIVO' && ($datos['condicion'] ?? '') === 'HABIDO',
    ];
}