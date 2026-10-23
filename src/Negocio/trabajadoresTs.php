<?php
require_once __DIR__ . '/../Datos/trabajadoresTs.php';
require_once __DIR__ . '/../Datos/trabajadores.php';
require_once __DIR__ . '/../Datos/empresas.php';
require_once __DIR__ . '/../Datos/empresaCliente.php';
require_once __DIR__ . '/../Datos/areas.php';
require_once __DIR__ . '/../Datos/cargos.php';
require_once __DIR__ . '/../Datos/usuarios.php';
require_once __DIR__ . '/../../config/database.php';

function buscarTrabajadorTs(string $dni): array
{
    $dni = trim($dni);
    if (!preg_match('/^\d{8}$/', $dni)) {
        return ['ok' => false, 'error' => 'El DNI debe tener 8 dígitos numéricos.'];
    }

    // Nos aseguramos que no lo tengamos ya registrado
    $trabajador = obtenerTrabajadorPorDni($dni);
    if ($trabajador) return ['ok' => false, 'error' => "El trabajador ya se encuentra registrado como: {$trabajador['nombres']}  DNI:{$dni}]"];

    $trabajadorTs = obtenerTrabajadorTsPorDni($dni);
    if (!$trabajadorTs) return ['ok' => false, 'error' => 'No se encontró un trabajador registrado con ese dni.'];

    // mapeamos empresa matriz localmente
    $matrizLocal = obtenerEmpresaPorRuc($trabajadorTs['matriz_ruc']);

    // nos aseguramos si es cliente
    $esCliente = ($trabajadorTs['cliente_ruc'] !== $trabajadorTs['matriz_ruc']);

    // verificacion de cuenta bancaria (BBVA)
    $datosCuenta = resolverCuentaBbvaTs($dni);

    return [
        'ok' => true,
        'datos' => [
            'dni' => $trabajadorTs['dni'],
            'nombres' => $trabajadorTs['nombres'],
            'area' => $trabajadorTs['area'],
            'cargo' => $trabajadorTs['cargo'],
            'matriz_id' => $matrizLocal['id'],
            'matriz_razon_social' => $matrizLocal['razon_social'],
            'esCliente' => $esCliente,
            'cliente_ruc' => $esCliente ? $trabajadorTs['cliente_ruc'] : null,
            'cliente_razon_social' => $esCliente ? $trabajadorTs['cliente_razon_social'] : null,
            'cuenta_bbva' => $datosCuenta['cuenta'],
            'advertencia_cuenta' => $datosCuenta['advertencia']
        ]
    ];
}

function sincronizarTrabajador(array $datosTrabajador, int $creador): array
{
    $pdo = obtenerConexion();

    $esAdmin = filter_var($datosTrabajador['es_admin'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $username = trim($datosTrabajador['username'] ?? '');
    $correo = strtolower(trim($datosTrabajador['correo'] ?? ''));
    $password = $datosTrabajador['password'] ?? '';
    $rol = $datosTrabajador['rol'] ?? '';
    $rolesValidos = [
        'SOLICITANTE',
        'TESORERIA',
        'CONTABILIDAD',
        'GERENCIA'
    ];
    $datosCuenta = resolverCuentaBbvaTs($datosTrabajador['dni']);
    $cuentaBbva = $datosCuenta['cuenta'];

    if ($username === '' || $password === '' || $rol === '') return ['ok' => false, 'error' => 'Completa usuario, contraseña y rol.'];
    if (!in_array($rol, $rolesValidos, true)) return ['ok' => false, 'error' => 'El rol seleccionado no es válido.'];
    if (buscarUsuarioPorUsername($username)) return ['ok' => false, 'error' => 'El nombre de usuario ya existe.'];
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Ingresa un correo válido.'];
    if (buscarUsuarioPorCorreo($correo)) return ['ok' => false, 'error' => 'Ese correo ya está registrado.']; 

    try {
        $pdo->beginTransaction();

        $empresaClienteId = null;
        if (!empty($datosTrabajador['esCliente']) && !empty($datosTrabajador['cliente_ruc'])) {
            $clienteLocal = obtenerEmpresasClientePorRuc($datosTrabajador['cliente_ruc']);
            $empresaClienteId = $clienteLocal
                ? (int)$clienteLocal['id']
                : crearEmpresaCliente($datosTrabajador['cliente_ruc'], $datosTrabajador['cliente_razon_social']);
        }

        $cargoId = !empty($datosTrabajador['cargo'])
            ? obtenerOcrearCargoPorNombre($datosTrabajador['cargo'])
            : null;

        $areaId = !empty($datosTrabajador['area'])
            ? obtenerOcrearAreaPorNombre($datosTrabajador['matriz_id'], $datosTrabajador['area'])
            : null;

        if ($areaId === null) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'El trabajador debe tener un área asignada.'];
        }

        $esJefe = filter_var($datosTrabajador['es_jefe'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($esJefe && existeJefe($areaId)) {
            $pdo->rollBack();
            return [
                'ok' => false,
                'error' => 'Esta área ya tiene un jefe asignado.'
            ];
        }

        $trabajadorId = crearTrabajador(
            $datosTrabajador['matriz_id'],
            $empresaClienteId,
            $areaId,
            $cargoId,
            $datosTrabajador['dni'],
            $datosTrabajador['nombres'],
            $cuentaBbva,
            $esJefe,
        );

        crearUsuario(
            $trabajadorId,
            $username,
            $correo,
            $password,
            $rol,
            $esAdmin,
            $creador
        );

        $pdo->commit();

        return [
            'ok' => true,
            'trabajador_id' => $trabajadorId,
            'mensaje' => 'Trabajador y usuario creados correctamente.'
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()){
            $pdo->rollBack();
        }
        error_log('Error al sincronizar trabajador y usuario: ' . $e->getMessage());
        return [
            'ok' => false,
            'error' => 'No se pudo completar el registro.'
        ];
    }
}

function resolverCuentaBbvaTs(string $dni): array
{
    $cuenta = obtenerCuentaBbvaTsPorDni($dni);

    // Nos aseguramos de tener una sola válida
    $cuentaValida = array_values(array_filter($cuenta, fn(string $cuenta): bool => preg_match('/^\d{20}$/', $cuenta) === 1));

    if (count($cuentaValida) === 1) return ['cuenta' => $cuentaValida[0], 'advertencia' => null];
    if (count($cuentaValida) > 1)  return ['cuenta' => null, 'advertencia' => 'Se encontraron varias cuentas BBVA activas. Revisar.'];

    return ['cuenta' => null, 'advertencia' => 'No se encontró una cuenta válida.'];
}
