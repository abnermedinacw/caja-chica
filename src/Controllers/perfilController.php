<?php
require_once __DIR__ . '/../Negocio/auth.php';
requiereSesion();

require_once __DIR__ . '/../Datos/usuarios.php';
require_once __DIR__ . '/../Vistas/helpers.php';
require_once __DIR__ . '/../Vistas/perfilView.php';

$perfil = obtenerPerfilUsuario((int)$_SESSION['usuario_id']);

if (!$perfil) {
    http_response_code(403);
    die('Perfil no encontrado.');
}

$mensaje = $_SESSION['perfil_mensaje'] ?? null;
unset($_SESSION['perfil_mensaje']);

vistaPerfil($perfil, $mensaje);