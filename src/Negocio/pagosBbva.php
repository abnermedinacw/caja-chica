<?php

require_once __DIR__ . '/../Datos/datosReembolso.php';
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/../Datos/desembolsosFondoBbva.php';
require_once __DIR__ . '/formatoBbva.php';
require_once __DIR__ . '/../../config/database.php';

function enmascararCuentaBbva(?string $cuenta): string
{
    if (!$cuenta) return '-';
    return str_repeat('*', max(0, strlen($cuenta) - 6)) . substr($cuenta, -6);
}

// La construcción del detalle BBVA es común para fondos y cajas chicas.
function construirPagoBbva(array $registro, string $descripcion): array
{
    if (!preg_match('/^\d{8}$/', (string) ($registro['dni'] ?? ''))) {
        throw new DomainException("El DNI de {$descripcion} no es válido.");
    }

    if (!preg_match('/^\d{20}$/', (string) ($registro['cuenta_bbva'] ?? ''))) {
        throw new DomainException("{$descripcion} no tiene una cuenta BBVA válida.");
    }

    $importeCentimos = decimalToCentimosBbva((string) ($registro['importe'] ?? 0));
    if ($importeCentimos <= 0) {
        throw new DomainException("{$descripcion} tiene un importe inválido.");
    }

    return [
        'dni' => $registro['dni'],
        'trabajador' => $registro['trabajador'],
        'cuenta_bbva' => $registro['cuenta_bbva'],
        'importe_centimos' => $importeCentimos,
    ];
}

function crearArchivoTxtBbva(array $empresa, array $pagos, string $prefijo): array
{
    $cuentaCargo = trim((string) ($empresa['cuenta_cargo_bbva'] ?? ''));
    if (!preg_match('/^\d{20}$/', $cuentaCargo)) throw new DomainException('La empresa no tiene una cuenta de cargo BBVA válida.');

    return [
        'contenido' => generarContenidoTxtBbva($cuentaCargo, $pagos),
        'nombre_archivo' => sprintf('%s_%s.txt', $prefijo, date('Ymd_His')),
    ];
}

function guardarCuentaEmpresaBbva(
    int $empresaId,
    string $cuenta,
    string $confirmacionCuenta
): array {
    $cuenta = preg_replace('/\s+/', '', $cuenta);
    $confirmacionCuenta = preg_replace('/\s+/', '', $confirmacionCuenta);

    if ($empresaId <= 0) return ['ok' => false, 'error' => 'Selecciona una empresa.'];
    if (!preg_match('/^\d{20}$/', $cuenta)) return ['ok' => false, 'error' => 'La cuenta de cargo debe contener exactamente 20 dígitos.'];
    if ($cuenta !== $confirmacionCuenta) return ['ok' => false, 'error' => 'Las cuentas ingresadas no coinciden.'];
    if (!obtenerEmpresaPagoBbva($empresaId)) return ['ok' => false, 'error' => 'Empresa no encontrada.'];

    actualizarCuentaCargoBbva($empresaId, $cuenta);
    return ['ok' => true];
}

function obtenerEstadoPagosBbva(int $empresaId): array
{
    $empresa = obtenerEmpresaPagoBbva($empresaId);
    if (!$empresa) return ['ok' => false, 'error' => 'Empresa no encontrada.'];

    $pagos = listarCajasPagosBbva($empresaId);

    foreach ($pagos as &$caja) {
        $cuentaValida = preg_match('/^\d{20}$/', $caja['cuenta_bbva'] ?? '') === 1;
        $importeValido = decimalToCentimosBbva((string)$caja['importe']) > 0;
        $caja['descargada'] = (int)$caja['txt_bbva_descargado'] === 1;

        $caja['habilitada'] = !$caja['descargada'] && $cuentaValida && $importeValido;
        $caja['cuenta_enmascarada'] = enmascararCuentaBbva($caja['cuenta_bbva']);

        if ($caja['descargada']) $caja['motivo_invalido'] = 'TXT descargado';
        elseif (!$cuentaValida) $caja['motivo_invalido'] = 'Cuenta BBVA no sincronizada o inválida';
        elseif (!$importeValido) $caja['motivo_invalido'] = 'El importe debe ser mayor que cero.';
        else $caja['motivo_invalido'] = null;

        unset($caja['cuenta_bbva']);
    }
    unset($caja);

    $fondos = listarFondosDesembolsoBbva($empresaId);
    foreach ($fondos as &$fondo) {
        $cuentaValida = preg_match('/^\d{20}$/', $fondo['cuenta_bbva'] ?? '') === 1;
        $importeValido = decimalToCentimosBbva((string) $fondo['importe']) > 0;

        $fondo['habilitada'] = $fondo['estado'] === 'PENDIENTE_DESEMBOLSO'
            && $cuentaValida
            && $importeValido;
        $fondo['cuenta_enmascarada'] = enmascararCuentaBbva($fondo['cuenta_bbva']);
        $fondo['motivo_invalido'] = $fondo['habilitada']
            ? null
            : ($fondo['estado'] !== 'PENDIENTE_DESEMBOLSO'
                ? 'TXT generado'
                : (!$cuentaValida
                    ? 'Cuenta BBVA no sincronizada o inválida'
                    : 'El importe debe ser mayor que cero.'));

        unset($fondo['cuenta_bbva']);
    }
    unset($fondo);

    $empresa['cuenta_enmascarada'] = enmascararCuentaBbva($empresa['cuenta_cargo_bbva']);

    return [
        'ok' => true,
        'empresa' => $empresa,
        'pagos' => $pagos,
        'fondos' => $fondos
    ];
}

function normalizarIdsBbva(array $ids): array
{
    return array_values(array_unique(array_filter(
        array_map('intval', $ids),
        fn(int $id): bool => $id > 0
    )));
}

function prepararPagosCajasBbva(int $empresaId, array $cajasIds): array
{
    $cajas = bloquearCajasPagosBbva($cajasIds);
    if (count($cajas) !== count($cajasIds)) {
        throw new DomainException('Una o más cajas seleccionadas no existen.');
    }

    $pagos = [];
    foreach ($cajas as $caja) {
        if ((int) $caja['empresa_id'] !== $empresaId) {
            throw new DomainException('Todas las cajas deben pertenecer a la misma empresa.');
        }
        if ($caja['estado_general'] !== 'VISADO') {
            throw new DomainException("La caja {$caja['numero']} todavía no está visada.");
        }
        if ((int) $caja['txt_bbva_descargado'] === 1) {
            throw new DomainException("La caja {$caja['numero']} ya fue descargada.");
        }

        $pagos[] = construirPagoBbva($caja, "el trabajador de la caja {$caja['numero']}");
    }

    return $pagos;
}

function prepararPagosFondosBbva(int $empresaId, array $fondosIds): array
{
    $fondos = bloquearFondosDesembolsoBbva($fondosIds);
    if (count($fondos) !== count($fondosIds)) {
        throw new DomainException('Uno o más fondos no existen.');
    }

    $pagos = [];
    foreach ($fondos as $fondo) {
        if ((int) $fondo['empresa_id'] !== $empresaId) {
            throw new DomainException('Todos los fondos deben pertenecer a la misma empresa.');
        }
        if ($fondo['estado'] !== 'PENDIENTE_DESEMBOLSO') {
            throw new DomainException("El fondo {$fondo['id']} ya fue desembolsado.");
        }

        $pagos[] = construirPagoBbva($fondo, "el fondo de {$fondo['trabajador']}");
    }

    return $pagos;
}

function generarTxtPagosBbva(
    int $empresaId,
    array $ids,
    string $origen,
    int $usuarioId = 0
): array {
    $pdo = obtenerConexion();
    $ids = normalizarIdsBbva($ids);

    if ($empresaId <= 0 || $ids === [] || !in_array($origen, ['caja', 'fondo'], true)) {
        return ['ok' => false, 'error' => 'Selecciona una empresa y al menos un pago válido.'];
    }

    try {
        $pdo->beginTransaction();
        $empresa = obtenerEmpresaPagoBbva($empresaId, true);
        if (!$empresa) throw new DomainException('Empresa no encontrada.');

        $pagos = $origen === 'caja'
            ? prepararPagosCajasBbva($empresaId, $ids)
            : prepararPagosFondosBbva($empresaId, $ids);
        $prefijo = $origen === 'caja' ? 'PAGO_CAJA_CHICA' : 'DESEMBOLSO_FONDOS';
        $archivo = crearArchivoTxtBbva($empresa, $pagos, $prefijo);

        if ($origen === 'caja') {
            marcarTxtDescargado($ids);
            foreach ($ids as $cajaId) {
                registrarEventoResumen(
                    $cajaId,
                    'TXT_DESCARGADO',
                    $usuarioId,
                    null,
                    'Descarga de txt BBVA registrada.'
                );
            }
        } else {
            marcarFondosDesembolsados($ids);
        }

        $pdo->commit();

        return ['ok' => true, ...$archivo];
    } catch (DomainException | InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        return ['ok' => false, 'error' => 'No se pudo generar el archivo.'];
    }
}

function generarTxtBbva(int $empresaId, array $cajasIds, int $usuarioId): array
{
    return generarTxtPagosBbva($empresaId, $cajasIds, 'caja', $usuarioId);
}

function generarTxtDesembolsoFondosBbva(int $empresaId, array $fondosIds): array
{
    return generarTxtPagosBbva($empresaId, $fondosIds, 'fondo');
}

function reactivarPagosBbva(int $empresaId, array $ids, string $origen, int $usuarioId = 0): array
{
    $pdo = obtenerConexion();
    $ids = normalizarIdsBbva($ids);

    if ($empresaId <= 0 || $ids === [] || !in_array($origen, ['caja', 'fondo'], true)) {
        return ['ok' => false, 'error' => 'Selecciona una empresa y al menos un pago válido.'];
    }

    try {
        $pdo->beginTransaction();

        $registros = $origen === 'caja'
            ? bloquearCajasPagosBbva($ids)
            : bloquearFondosDesembolsoBbva($ids);
        if (count($registros) !== count($ids)) {
            throw new DomainException('Uno o más pagos seleccionados no existen.');
        }

        foreach ($registros as $registro) {
            if ((int) $registro['empresa_id'] !== $empresaId) {
                throw new DomainException('Todos los pagos deben pertenecer a la empresa seleccionada.');
            }
            if ($origen === 'caja') {
                if ((int) $registro['txt_bbva_descargado'] !== 1) {
                    throw new DomainException("La caja {$registro['numero']} ya está habilitada.");
                }
            } else {
                if ($registro['estado'] !== 'ACTIVO' || !$registro['fecha_desembolso']) {
                    throw new DomainException("El fondo {$registro['id']} no tiene un desembolso registrado.");
                }
                if ((int) $registro['tiene_cajas'] === 1) {
                    throw new DomainException("El fondo {$registro['id']} ya tiene resúmenes y no puede reactivarse.");
                }
            }
        }

        if ($origen === 'caja') {
            reactivarTxtBbva($ids);
            foreach ($ids as $cajaId) {
                registrarEventoResumen($cajaId, 'TXT_REACTIVADO', $usuarioId, null, null);
            }
        } else {
            reactivarFondosDesembolsoBbva($ids);
        }

        $pdo->commit();
        return ['ok' => true];
    } catch (DomainException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        return ['ok' => false, 'error' => 'No se pudo reactivar el pago.'];
    }
}

function reactivarPagoTxtBbva(int $empresaId, array $cajasIds, int $usuarioId): array
{
    return reactivarPagosBbva($empresaId, $cajasIds, 'caja', $usuarioId);
}

function reactivarDesembolsoFondosBbva(int $empresaId, array $fondosIds): array
{
    return reactivarPagosBbva($empresaId, $fondosIds, 'fondo');
}
