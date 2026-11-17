<?php

// La web y los comandos PHP comparten la misma hora de trabajo.
date_default_timezone_set('America/Lima');

function obtenerConexion(): PDO
{
    static $pdo = null; // Guarda siempre la variable
    if ($pdo !== null)
    {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'localhost';
    $puerto = getenv('DB_PORT') ?: '3306';
    $bd = getenv('DB_DATABASE') ?: (getenv('DB_NAME') ?: 'caja_chica');
    $usuario = getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: 'root');
    $password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

    $dsn = "mysql:host=$host;port=$puerto;dbname=$bd;charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $usuario, $password, [
            PDO::ATTR_ERRMODE => PDO:: ERRMODE_EXCEPTION, // Captura la excepcion especifica
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Devuelve en un array solo nombre de las columnas
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-05:00'",
        ]);
        return $pdo;
    } catch (PDOException $e){
        die('Error de conexión a la base de datos' . $e->getMessage());
    }
}
