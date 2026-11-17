<?php
return [
    // Tokens para APIs de verificación de RUC (plan de contingencia)
    // - apiperu.dev: https://apiperu.dev/register
    // - chequea.pe (soporta RUC 10, 15, 17 y 20): https://chequea.pe/
    'apiperu' => [
        'token' => '',
        'url'   => 'https://apiperu.dev/api/ruc',
    ],
    'chequea' => [
        'token' => '',
        'url'   => 'https://api.chequea.pe/api/v1/ruc',
    ],
];
