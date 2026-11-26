<?php
require_once __DIR__ . '/../Datos/trabajadores.php';
require_once __DIR__ . '/../Datos/usuarios.php';

function actualizarCuentaBbva(int $trabajadorId, string $cuenta, string $confirmacion, bool $fijarPredeterminado = false): array
{
    $cuenta = preg_replace('/\D/', '', $cuenta) ?? '';
    $confirmacion = preg_replace('/\D/', '', $confirmacion) ?? '';

    if ($trabajadorId <= 0) return ['ok' => false, 'error' => 'No hay trabajador asociado.'];
    if (!preg_match('/^\d{20}$/', $cuenta)) return ['ok' => false, 'error' => 'La cuenta BBVA debe contener 20 dígitos.'];
    if ($cuenta !== $confirmacion) return ['ok' => false, 'error' => 'Las cuentas ingresadas no coinciden.'];

    actualizarCuentaBbvaTrabajador($trabajadorId, $cuenta, $fijarPredeterminado);

    return ['ok' => true, 'mensaje' => 'Tu cuenta BBVA fue guardada y quedó pendiente de validación por Tesorería.'];
}

function actualizarCuentaBcp(
    int $trabajadorId,
    string $tipoCuenta,
    string $cuenta,
    string $confirmacion,
    bool $fijarPredeterminado = false
): array {
    $tipoCuenta = strtoupper(trim($tipoCuenta));
    $cuenta = preg_replace('/\D/', '', $cuenta) ?? '';
    $confirmacion = preg_replace('/\D/', '', $confirmacion) ?? '';

    if ($trabajadorId <= 0) return ['ok' => false, 'error' => 'No hay trabajador asociado.'];
    if (!in_array($tipoCuenta, ['A', 'C'], true)) {
        return ['ok' => false, 'error' => 'Selecciona si la cuenta BCP es de Ahorros o Corriente.'];
    }
    $longitudEsperada = $tipoCuenta === 'A' ? 14 : 13;
    if (strlen($cuenta) !== $longitudEsperada || !preg_match('/^\d+$/', $cuenta)) {
        $etiqueta = $tipoCuenta === 'A' ? 'Ahorros (14 dígitos)' : 'Corriente (13 dígitos)';
        return ['ok' => false, 'error' => "La cuenta BCP {$etiqueta} debe contener exactamente {$longitudEsperada} dígitos."];
    }
    if ($cuenta !== $confirmacion) {
        return ['ok' => false, 'error' => 'Las cuentas BCP ingresadas no coinciden.'];
    }

    actualizarCuentaBcpTrabajador($trabajadorId, $cuenta, $tipoCuenta, $fijarPredeterminado);

    return ['ok' => true, 'mensaje' => 'Tu cuenta BCP fue guardada y quedó pendiente de validación por Tesorería.'];
}

function actualizarBancoPredeterminado(int $trabajadorId, string $banco): array
{
    $banco = strtoupper(trim($banco));
    if ($trabajadorId <= 0) return ['ok' => false, 'error' => 'No hay trabajador asociado.'];
    if (!in_array($banco, ['BBVA', 'BCP'], true)) {
        return ['ok' => false, 'error' => 'Selecciona un banco válido (BBVA o BCP).'];
    }

    $cuentas = obtenerCuentasTrabajador($trabajadorId);
    if (!$cuentas) return ['ok' => false, 'error' => 'Trabajador no encontrado.'];

    if ($banco === 'BBVA' && !preg_match('/^\d{20}$/', (string)($cuentas['cuenta_bbva'] ?? ''))) {
        return ['ok' => false, 'error' => 'Primero registra tu cuenta BBVA antes de elegirla como predeterminada.'];
    }
    if ($banco === 'BCP' && !preg_match('/^\d{13,14}$/', (string)($cuentas['cuenta_bcp'] ?? ''))) {
        return ['ok' => false, 'error' => 'Primero registra tu cuenta BCP antes de elegirla como predeterminada.'];
    }

    actualizarBancoPredeterminadoTrabajador($trabajadorId, $banco);
    return ['ok' => true, 'mensaje' => "Tu banco predeterminado ahora es {$banco}."];
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
