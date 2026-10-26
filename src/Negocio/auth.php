<?php

require_once __DIR__ . '/../Datos/usuarios.php';

function requiereSesion(): void
{
    if (empty($_SESSION['usuario_id'])) manejarPeticion();
    if (!usuarioActivo((int)$_SESSION['usuario_id'])) {
        cerrarSesion();
        manejarPeticion();
    }

    $now = time();
    $ultimaActividad = (int) ($_SESSION['ultima_actividad'] ?? $now);

    if (($now - $ultimaActividad) >= TIEMPO_INACTIVIDAD) {
        cerrarSesion();
        manejarPeticion();
    }

    $_SESSION['ultima_actividad'] = $now;
    $_SESSION['es_jefe'] = esJefe((int)$_SESSION['usuario_id']);
}

function manejarPeticion(): never
{
    $aceptarJson = str_contains(
        $_SERVER['HTTP_ACCEPT'] ?? '',
        'application/json'
    );

    $esAjax = strtolower(
        $_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''
    ) === 'xmlhttprequest';

    if ($aceptarJson || $esAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'ok' => false,
            'sesion_expirada' => true,
            'error' => 'Tu sesión terminó. Inicia sesión nuevamente.',
        ]);
        exit;
    }

    header('Location: ' . BASE_URL . '/login?sesion=expirada');
    exit;
}

function esAdministrador():bool
{
    return !empty($_SESSION['es_admin']);
}

function puedeVerHistorialAprobaciones(): bool
{
    return in_array($_SESSION['rol'] ?? '', ['CONTABILIDAD', 'GERENCIA', 'TESORERIA'], true)
        || !empty($_SESSION['es_jefe'])
        || tieneDecisionesAprobacion((int)($_SESSION['usuario_id'] ?? 0));
}


function tryLogin(string $username, string $password): array
{
    if($username === '' || $password === ''){
        return['ok' => false, 'error' => 'Usuario y contraseña son obligatorios'];
    }

    $usuario = buscarUsuarioPorUsername($username);

    $passwordCorrecto = $usuario && (
        hash_equals((string)$usuario['password'], $password)
        || password_verify($password, (string)$usuario['password'])
    );

    if (!$passwordCorrecto) {
        return ['ok' => false, 'error' => 'Usuario o contraseña incorrectos.'];
    }

    if($usuario['estado'] !== 'ACTIVO') {
        return['ok' => false, 'error' => 'Usuario inactivo. Contacte al administrador.'];
    }

    return ['ok' => true, 'usuario' => $usuario];
}

function iniciarSesionUsuario(array $usuario): void 
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['username'] = $usuario['username'];
    $_SESSION['rol'] = $usuario['rol'];
    $_SESSION['trabajador_id'] = $usuario['trabajador_id'];
    $_SESSION['trabajador_nombre'] = $usuario['trabajador_nombre'];
    $_SESSION['empresa_id'] = $usuario['empresa_id'];
    $_SESSION['area_id'] = $usuario['area_id'];
    $_SESSION['area_nombre'] = $usuario['area_nombre'];
    $_SESSION['cargo_nombre'] = $usuario['cargo_nombre'];
    $_SESSION['es_jefe'] = (bool)$usuario['es_jefe'];
    $_SESSION['es_admin'] = (bool)$usuario['es_admin'];
    $_SESSION['ultima_actividad'] = time();
}

function cerrarSesion(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 4200,
            'path' => $parametros['path'],
            'domain' => $parametros['domain'],
            'secure' => $parametros['secure'],
            'httponly' => $parametros['httponly'],
            'samesite' => 'Lax',
        ]);
    }

    session_destroy();
}
