<?php
function obtenerConexionTs(): PDO
{
    static $pdoTs = null;
    if ($pdoTs !== null) {
        return $pdoTs;
    }

    $host = getenv('DB_TS_HOST') ?: (getenv('DB_HOST') ?: 'localhost');
    $puerto = getenv('DB_TS_PORT') ?: (getenv('DB_PORT') ?: '3306');
    $bd = getenv('DB_TS_DATABASE') ?: 'tsoluciona';
    $usuario = getenv('DB_TS_USERNAME') ?: (getenv('DB_USERNAME') ?: 'root');
    $password = getenv('DB_TS_PASSWORD') !== false ? getenv('DB_TS_PASSWORD') : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '');

    $dsn = "mysql:host=$host;port=$puerto;dbname=$bd;charset=utf8mb4";

    try {
        $pdoTs = new PDO($dsn, $usuario, $password, [
            PDO::ATTR_ERRMODE => PDO:: ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdoTs;
    } catch (PDOException $e) {
        die('Error de conexión a la base de datos de TS' . $e->getMessage());
    }
}