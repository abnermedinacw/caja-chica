<?php
// 1. Iniciar la sesion PHP para inyectar datos de prueba
session_start();
$_SESSION['usuario_id'] = 99;
$_SESSION['rol'] = 'CONTROL';

// 2. Cargar de forma automatica la libreria de Sentry usando Composer
require_once __DIR__ . '/../vendor/autoload.php';

// 3. Inicializar Sentry con tu clave DSN unica
\Sentry\init([
    'dsn' => 'https://230b30a61ef10cf7caaadd23500890de@o4512408584978432.ingest.de.sentry.io/4512408654512208', 
    'environment' => 'development', 
]);

// 4. Enviar un evento manual para probar que todo funcione
//\Sentry\captureMessage('En q estas p neto.');

echo "Mensaje enviado a Sentry con exito. Revisa tu panel web.";
?>