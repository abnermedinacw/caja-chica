<?php

require_once __DIR__ . '/../Negocio/auth.php';
require_once __DIR__ . '/../Vistas/loginView.php';

if(isset($_SESSION['usuario_id'])){
    header('Location: ' . BASE_URL . '/home');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $resultado = tryLogin($username, $password);

    if ($resultado['ok']) {
        iniciarSesionUsuario($resultado['usuario']);
        header('Location: ' . BASE_URL . '/home');
        exit;
    }

    $error = $resultado['error'];
}

vistaLogin($error);