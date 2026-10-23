<?php
require_once __DIR__ . '/../config/database.php';
define('TIEMPO_INACTIVIDAD', 15*60);

ini_set(
    'session.gc_maxlifetime',
    (string) TIEMPO_INACTIVIDAD
);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')); // index.php por defecto en htaccess, se queda solo con el directorio

$rutas = require __DIR__ . '/../config/rutas.php';

$rutaSolicitada = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'); 

$prefijo = trim(BASE_URL, '/'); // Lo que descartamos para la ruta buscada

if ($prefijo !== '' && str_starts_with($rutaSolicitada, $prefijo)) {
    $rutaSolicitada = trim(substr($rutaSolicitada, strlen($prefijo)), '/');
}

if ($rutaSolicitada === '') {
    $rutaSolicitada = isset($_SESSION['usuario_id']) ? 'home' : 'login';
}

if (!isset($rutas[$rutaSolicitada])) {
    http_response_code(404);
    die('Pagina no encontrada.');
}

require __DIR__ . '/../src/Controllers/' . $rutas[$rutaSolicitada];
