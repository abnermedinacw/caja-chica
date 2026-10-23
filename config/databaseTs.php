<?php
function obtenerConexionTs(): PDO
{
    static $pdoTs = null;
    if ($pdoTs !== null) {
        return $pdoTs;
    }

    $host = 'localhost';
    $puerto = '3306';
    $bd = 'tsoluciona';
    $usuario = 'root';
    $password = '';

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