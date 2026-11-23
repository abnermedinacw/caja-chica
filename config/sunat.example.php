<?php
// Credenciales OAuth2 de la API de Consulta Integrada de Comprobantes de Pago (SUNAT)
// indexadas por el RUC de cada empresa registrada en la tabla `empresa`.
// Copiar este archivo como `config/sunat.php` (ignorado por Git) y completar las claves reales.
return [
    'empresas' => [
        '20482204440' => [ // T-SOLUCIONA
            'client_id'     => '',
            'client_secret' => '',
        ],
        '20606667818' => [ // PILSAC
            'client_id'     => '',
            'client_secret' => '',
        ],
        '20602405428' => [ // GRUPO T-SOLUCIONA
            'client_id'     => '',
            'client_secret' => '',
        ],
        '20607694550' => [ // X-TERNA
            'client_id'     => '',
            'client_secret' => '',
        ],
    ],
];
