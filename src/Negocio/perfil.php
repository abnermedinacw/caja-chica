<?php
require_once __DIR__ . '/../Datos/trabajadores.php';
require_once __DIR__ . '/../Datos/usuarios.php';

function actualizarCuentaBbva(int $trabajadorId, string $cuenta, string $confirmacion): array
{
    $cuenta = preg_replace('/\D/', '', $cuenta);
    $confirmacion = preg_replace('/\D/', '', $confirmacion);

    if ($trabajadorId <= 0) return ['ok' => false, 'error' => 'No hay trabajador asociado'];
    if (!preg_match('/^\d{20}$/', $cuenta)) return ['ok' => false, 'error' => 'La cuenta BBVA debe contener 20 dígitos'];
    if ($cuenta !== $confirmacion) return ['ok' => false, 'error' => 'Las cuentas ingresadas no coinciden.'];
    
    actualizarCuentaBbvaTrabajador($trabajadorId, $cuenta);

    return ['ok' => true, 'mensaje' => 'Tu cuenta BBVA fue actualizada.'];
}

function actualizarCorreoPerfil(int $usuarioId, string $correo, string $confirmacion): array
{
    $correo = mb_strtolower(trim($correo));
    $confirmacion = mb_strtolower(trim($confirmacion));

    if ($usuarioId <= 0 || !obtenerPerfilUsuario($usuarioId)) {
        return ['ok' => false, 'error' => 'Usuario no encontrado.'];
    }
    if (strlen($correo) > 254 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Ingresa un correo válido.'];
    }
    if ($correo !== $confirmacion) {
        return ['ok' => false, 'error' => 'Los correos no coinciden.'];
    }

    $existente = buscarUsuarioPorCorreo($correo);
    if ($existente && (int)$existente['id'] !== $usuarioId) {
        return ['ok' => false, 'error' => 'Ese correo ya está registrado.'];
    }

    actualizarCorreoUsuario($usuarioId, $correo);
    return ['ok' => true, 'mensaje' => 'Tu correo fue actualizado.'];
}
