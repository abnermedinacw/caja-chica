<?php
require_once __DIR__ . '/../Datos/trabajadoresTs.php';
require_once __DIR__ . '/../Datos/trabajadores.php';
require_once __DIR__ . '/../Datos/empresas.php';
require_once __DIR__ . '/../Datos/empresaCliente.php';
require_once __DIR__ . '/../Datos/areas.php';
require_once __DIR__ . '/../Datos/cargos.php';
require_once __DIR__ . '/../Datos/usuarios.php';
require_once __DIR__ . '/../../config/database.php';

function prepararDatosTrabajadorTs(string $dni): array
{
    $dni = trim($dni);
    if (!preg_match('/^\d{8}$/', $dni)) return ['ok' => false, 'error' => 'El DNI debe tener 8 dígitos numéricos.'];

    try {
        $ts = obtenerTrabajadorTsPorDni($dni);
        if (!$ts) throw new DomainException('No se encontró al trabajador activo en TS.');
        $matriz = obtenerEmpresaPorRuc(trim((string)$ts['matriz_ruc']));
        if (!$matriz) throw new DomainException('Empresa matriz no registrada.');

        $areaTsId = (int)($ts['area_ts_id'] ?? 0);
        $area = trim((string)($ts['area'] ?? ''));
        if ($areaTsId > 0 && $area === '') throw new DomainException('El área no existe en bd externa.');

        $grupos = $areaTsId > 0 ? obtenerGruposAreasTs($areaTsId) : [];
        if (count($grupos) > 1) throw new DomainException('El área está en varios grupos.');
        $gruposDirigidos = obtenerGruposDirigidosTs((int)$ts['trabajador_ts_id']);

        $jefeDni = '';
        $jefeNombre = '';
        if ($grupos) {
            $grupo = $grupos[0];
            $jefeDni = trim((string)($grupo['jefe_dni'] ?? ''));
            $jefeNombre = trim((string)($grupo['jefe_nombre'] ?? ''));
            if (empty($grupo['jefe_activo']) || !preg_match('/^\d{8}$/', $jefeDni)) throw new DomainException('El grupo de áreas ' . $grupo['grupo_nombre'] . ' no tiene un jefe activo válido en TS.');
        } elseif ($gruposDirigidos) {
            // Conserva el salto de Jefatura para un jefe reconocido en TS,
            // cuando su propia área no define a otro responsable.
            $jefeDni = $dni;
            $jefeNombre = $ts['nombres'];
        }

        $jefeLocal = $jefeDni !== '' ? obtenerResponsableActivoDni($jefeDni) : null;
        $errorJefe = $jefeDni !== '' && $jefeDni !== $dni && !$jefeLocal
            ? "Registra o activa al jefe {$jefeNombre} (DNI {$jefeDni}) en caja chica."
            : '';

        $esCliente = trim((string)$ts['cliente_ruc']) !== trim((string)$ts['matriz_ruc']);
        $cuenta = resolverCuentaBancariaTs($dni);
        return ['ok' => true, 'datos' => [
            'dni' => $dni,
            'nombres' => $ts['nombres'],
            'cargo' => $ts['cargo'],
            'area' => $area,
            'area_ts' => $area !== '',
            'area_id' => null,
            'matriz_id' => (int)$matriz['id'],
            'matriz_razon_social' => $matriz['razon_social'],
            'esCliente' => $esCliente,
            'cliente_ruc' => $esCliente ? trim((string)$ts['cliente_ruc']) : null,
            'cliente_razon_social' => $esCliente ? $ts['cliente_razon_social'] : null,
            'cuenta_bbva' => $cuenta['cuenta_bbva'],
            'cuenta_bcp' => $cuenta['cuenta_bcp'],
            'tipo_cuenta_bcp' => $cuenta['tipo_cuenta_bcp'],
            'banco_predeterminado' => $cuenta['banco_predeterminado'],
            'cuenta_resumen' => $cuenta['cuenta_resumen'],
            'advertencia_cuenta' => $cuenta['advertencia'],
            'jefe_ts' => $jefeDni !== '',
            'jefe_dni' => $jefeDni,
            'jefe_nombre' => $jefeNombre,
            'jefe_usuario_id' => $jefeLocal ? (int)$jefeLocal['usuario_id'] : null,
            'error_jefe' => $errorJefe,
            'grupos_a_cargo' => array_column($gruposDirigidos, 'nombre'),
            'codigo_ts' => trim((string)($ts['platra_codigo'] ?? ''))
        ]];
    } catch (DomainException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (Throwable $e) {
        error_log('Consulta de trabajador TS: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'No se pudo consultar la información.'];
    }
}
function buscarTrabajadorTs(string $dni): array
{
    $dni = trim($dni);
    if (obtenerTrabajadorPorDni($dni)) return ['ok' => false, 'error' => 'El trabajador ya está registrado.'];
    return prepararDatosTrabajadorTs($dni);
}

function sincronizarTrabajador(array $formulario, int $creador): array
{
    $username = trim((string)($formulario['username'] ?? ''));
    $correo = strtolower(trim((string)($formulario['correo'] ?? '')));
    $password = (string)($formulario['password'] ?? '');
    $rol = (string)($formulario['rol'] ?? '');
    $esAdmin = filter_var($formulario['es_admin'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($username === '' || $password === '' || $rol === '') return ['ok' => false, 'error' => 'Completa usuario y contraseña.'];
    if (!in_array($rol, ['SOLICITANTE', 'TESORERIA', 'CONTABILIDAD', 'GERENCIA'], true)) return ['ok' => false, 'error' => 'El rol seleccionado no es válido.'];
    if (buscarUsuarioPorUsername($username)) return ['ok' => false, 'error' => 'El nombre de usuario ya existe.'];
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Ingresa un correo válido.'];
    if (buscarUsuarioPorCorreo($correo)) return ['ok' => false, 'error' => 'Ese correo ya está registrado.']; 

    $resultado = buscarTrabajadorTs((string)($formulario['dni'] ?? ''));
    if (!$resultado['ok']) return $resultado;
    $datos = $resultado['datos'];
    if ($datos['error_jefe'] !== '') {
        return ['ok' => false, 'error' => $datos['error_jefe']];
    }

    $jefeUsuarioId = $datos['jefe_usuario_id'];
    if (!$datos['jefe_ts']) {
        $jefeUsuarioId = (int)($formulario['jefe_usuario_id'] ?? 0);
        if (!obtenerResponsableJefeValido($jefeUsuarioId, (string)$datos['dni'])) {
            return ['ok' => false, 'error' => 'Selecciona un jefe con usuario activo.'];
        }
    }

    $areaExistente = null;
    if (!$datos['area_ts']) {
        $areaExistente = obtenerAreaPorIdEmpresa((int)($formulario['area_id'] ?? 0), $datos['matriz_id']);
        if (!$areaExistente) return ['ok' => false, 'error' => 'Selecciona un área registrada de la matriz del trabajador.'];
    }

    $pdo = obtenerConexion();
    try {
        $pdo->beginTransaction();
        $empresaClienteId = null;

        if ($datos['esCliente']) {
            $cliente = obtenerEmpresasClientePorRuc($datos['cliente_ruc']);
            $empresaClienteId = $cliente ? (int)$cliente['id'] : crearEmpresaCliente($datos['cliente_ruc'], $datos['cliente_razon_social']);
        }

        $areaId = $areaExistente ? (int)$areaExistente['id'] : obtenerOcrearAreaPorNombre($datos['matriz_id'], $datos['area']);
        $cargoId = !empty($datos['cargo']) ? obtenerOcrearCargoPorNombre($datos['cargo']) : null;
        $trabajadorId = crearTrabajador(
            $datos['matriz_id'],
            $empresaClienteId,
            $areaId,
            $cargoId,
            $datos['dni'],
            $datos['nombres'],
            $datos['cuenta_bbva'],
            $datos['codigo_ts'] !== '' ? $datos['codigo_ts'] : null,
            $datos['cuenta_bcp'],
            $datos['tipo_cuenta_bcp'],
            $datos['banco_predeterminado']
        );

        $usuarioId = crearUsuario(
            $trabajadorId,
            $username,
            $correo,
            $password,
            $rol,
            $esAdmin,
            $creador
        );

        if ($datos['jefe_dni'] === $datos['dni']) {
            $jefeUsuarioId = $usuarioId;
        }
        asignarJefeTrabajador($trabajadorId, $jefeUsuarioId);

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

function resolverCuentaBancariaTs(string $dni): array
{
    $filas = obtenerCuentasBancariasTsPorDni($dni);
    $bbvaValidas = [];
    $bcpValidas = [];

    foreach ($filas as $fila) {
        $codigo = trim((string)($fila['banco_codigo'] ?? ''));
        $cuenta = preg_replace('/\D/', '', (string)($fila['cuenta'] ?? '')) ?? '';

        if ($codigo === '11' && preg_match('/^\d{20}$/', $cuenta) === 1) {
            $bbvaValidas[$cuenta] = $cuenta;
        } elseif ($codigo === '02' && preg_match('/^\d{14}$/', $cuenta) === 1) {
            $bcpValidas[$cuenta] = ['cuenta' => $cuenta, 'tipo' => 'A'];
        } elseif ($codigo === '02' && preg_match('/^\d{13}$/', $cuenta) === 1) {
            $bcpValidas[$cuenta] = ['cuenta' => $cuenta, 'tipo' => 'C'];
        }
    }

    $bbvaValidas = array_values($bbvaValidas);
    $bcpValidas = array_values($bcpValidas);

    $cuentaBbva = count($bbvaValidas) === 1 ? $bbvaValidas[0] : null;
    $cuentaBcp = count($bcpValidas) === 1 ? $bcpValidas[0]['cuenta'] : null;
    $tipoCuentaBcp = count($bcpValidas) === 1 ? $bcpValidas[0]['tipo'] : null;

    $advertencias = [];
    if (count($bbvaValidas) > 1) {
        $advertencias[] = 'Se encontraron varias cuentas BBVA activas en TS.';
    }
    if (count($bcpValidas) > 1) {
        $advertencias[] = 'Se encontraron varias cuentas BCP activas en TS.';
    }

    $bancoPredeterminado = null;
    $resumenPartes = [];
    if ($cuentaBbva !== null) {
        $bancoPredeterminado = 'BBVA';
        $resumenPartes[] = 'BBVA · ************' . substr($cuentaBbva, -8);
    }
    if ($cuentaBcp !== null) {
        if ($bancoPredeterminado === null) {
            $bancoPredeterminado = 'BCP';
        }
        $etiquetaTipo = $tipoCuentaBcp === 'C' ? 'Corriente' : 'Ahorros';
        $resumenPartes[] = "BCP ({$etiquetaTipo}) · " . str_repeat('*', max(0, strlen($cuentaBcp) - 6)) . substr($cuentaBcp, -6);
    }

    if ($cuentaBbva === null && $cuentaBcp === null && $advertencias === []) {
        $advertencias[] = 'No se encontró una cuenta BBVA o BCP válida en TS.';
    }

    return [
        'cuenta_bbva' => $cuentaBbva,
        'cuenta_bcp' => $cuentaBcp,
        'tipo_cuenta_bcp' => $tipoCuentaBcp,
        'banco_predeterminado' => $bancoPredeterminado,
        'cuenta_resumen' => $resumenPartes ? implode(' / ', $resumenPartes) : null,
        'advertencia' => $advertencias ? implode(' ', $advertencias) : null,
    ];
}