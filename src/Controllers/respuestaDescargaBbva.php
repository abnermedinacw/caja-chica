<?php

function responderDescargaBbva(array $resultado): never
{
    if (!$resultado['ok']) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $contenido = $resultado['contenido'];
    header('Content-Type: text/plain; charset=US-ASCII');
    header('Content-Disposition: attachment; filename="' . $resultado['nombre_archivo'] . '"');
    header('Content-Length: ' . strlen($contenido));
    header('Cache-Control: no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo $contenido;
    exit;
}
