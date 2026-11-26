<?php

require_once __DIR__ . '/../Datos/datosReembolso.php';
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/../Datos/desembolsosFondoBbva.php';
require_once __DIR__ . '/../Datos/trabajadores.php';
require_once __DIR__ . '/formatoBbva.php';
require_once __DIR__ . '/formatoBcp.php';
require_once __DIR__ . '/../../config/database.php';

function enmascararCuentaBbva(?string $cuenta): string
{
    $cuenta = preg_replace('/\D/', '', (string)$cuenta) ?? '';
    if ($cuenta === '') return '-';
    return str_repeat('*', max(0, strlen($cuenta) - 6)) . substr($cuenta, -6);
}

function normalizarBancoPago(string $banco): string
{
    $banco = strtoupper(trim($banco));
    if (!in_array($banco, ['BBVA', 'BCP'], true)) {
        throw new DomainException('Selecciona un banco válido (BBVA o BCP).');
    }
    return $banco;
}

function resolverBancoPredeterminadoRegistro(array $registro): ?string
{
    $pred = strtoupper(trim((string)($registro['banco_predeterminado'] ?? '')));
    if (in_array($pred, ['BBVA', 'BCP'], true)) {
        return $pred;
    }
    if (preg_match('/^\d{20}$/', (string)($registro['cuenta_bbva'] ?? '')) === 1) {
        return 'BBVA';
    }
    if (preg_match('/^\d{13,14}$/', (string)($registro['cuenta_bcp'] ?? '')) === 1) {
        return 'BCP';
    }
    return null;
}

function evaluarCuentaDestinoRegistro(array $registro, ?string $bancoForzado = null): array
{
    $bancoDestino = $bancoForzado ?? resolverBancoPredeterminadoRegistro($registro);

    if ($bancoDestino === 'BBVA') {
        $cuenta = trim((string)($registro['cuenta_bbva'] ?? ''));
        $formatoValido = preg_match('/^\d{20}$/', $cuenta) === 1;
        $verificada = (int)($registro['cuenta_bbva_verificada'] ?? 1) === 1;
        $motivo = !$formatoValido
            ? 'Cuenta BBVA no registrada o inválida'
            : (!$verificada ? 'Cuenta BBVA pendiente de validación por Tesorería' : null);

        return [
            'banco_destino' => 'BBVA',
            'cuenta_enmascarada' => $formatoValido ? enmascararCuentaBbva($cuenta) : '-',
            'tipo_cuenta_etiqueta' => null,
            'cuenta_valida' => $formatoValido && $verificada,
            'cuenta_verificada' => $verificada,
            'motivo_cuenta' => $motivo,
        ];
    }

    if ($bancoDestino === 'BCP') {
        $cuenta = trim((string)($registro['cuenta_bcp'] ?? ''));
        $tipo = strtoupper(trim((string)($registro['tipo_cuenta_bcp'] ?? '')));
        if ($tipo === '' && strlen($cuenta) === 14) $tipo = 'A';
        if ($tipo === '' && strlen($cuenta) === 13) $tipo = 'C';

        $formatoValido = validarCuentaBcpPorTipo($cuenta, $tipo);
        $verificada = (int)($registro['cuenta_bcp_verificada'] ?? 1) === 1;
        $etiquetaTipo = $tipo === 'C' ? 'Corriente' : ($tipo === 'M' ? 'Maestra' : ($tipo === 'A' ? 'Ahorros' : null));
        $motivo = !$formatoValido
            ? 'Cuenta BCP no registrada o inválida'
            : (!$verificada ? 'Cuenta BCP pendiente de validación por Tesorería' : null);

        return [
            'banco_destino' => 'BCP',
            'cuenta_enmascarada' => $formatoValido ? enmascararCuentaBbva($cuenta) : '-',
            'tipo_cuenta_etiqueta' => $etiquetaTipo,
            'cuenta_valida' => $formatoValido && $verificada,
            'cuenta_verificada' => $verificada,
            'motivo_cuenta' => $motivo,
        ];
    }

    return [
        'banco_destino' => null,
        'cuenta_enmascarada' => '-',
        'tipo_cuenta_etiqueta' => null,
        'cuenta_valida' => false,
        'cuenta_verificada' => false,
        'motivo_cuenta' => 'Sin cuenta bancaria registrada',
    ];
}

// La construcción del detalle de pago es común para fondos y cajas chicas, para BBVA o BCP.
function construirPagoBbva(array $registro, string $descripcion, string $banco = 'BBVA'): array
{
    $banco = normalizarBancoPago($banco);

    if (!preg_match('/^\d{8}$/', (string)($registro['dni'] ?? ''))) {
        throw new DomainException("El DNI de {$descripcion} no es válido.");
    }

    $predeterminado = resolverBancoPredeterminadoRegistro($registro);
    if ($predeterminado !== $banco) {
        $etiquetaPred = $predeterminado ?: 'ninguno';
        throw new DomainException("{$descripcion} tiene configurado como banco predeterminado {$etiquetaPred}, no {$banco}.");
    }

    $importeCentimos = decimalToCentimosBbva((string)($registro['importe'] ?? 0));
    if ($importeCentimos <= 0) {
        throw new DomainException("{$descripcion} tiene un importe inválido.");
    }

    $pago = [
        'dni' => $registro['dni'],
        'trabajador' => $registro['trabajador'],
        'importe_centimos' => $importeCentimos,
        'codigo_planilla' => $registro['codigo_planilla'] ?? null,
        'fecha_visado_tesoreria' => $registro['fecha_visado_tesoreria'] ?? null,
        'fecha_asignacion' => $registro['fecha_asignacion'] ?? null,
    ];

    if ($banco === 'BBVA') {
        $cuenta = trim((string)($registro['cuenta_bbva'] ?? ''));
        if (!preg_match('/^\d{20}$/', $cuenta)) {
            throw new DomainException("{$descripcion} no tiene una cuenta BBVA válida.");
        }
        if ((int)($registro['cuenta_bbva_verificada'] ?? 1) !== 1) {
            throw new DomainException("La cuenta BBVA de {$descripcion} todavía no ha sido validada por Tesorería.");
        }
        $pago['cuenta_bbva'] = $cuenta;
    } else {
        $cuenta = trim((string)($registro['cuenta_bcp'] ?? ''));
        $tipoCuenta = strtoupper(trim((string)($registro['tipo_cuenta_bcp'] ?? '')));
        if ($tipoCuenta === '' && strlen($cuenta) === 14) $tipoCuenta = 'A';
        if ($tipoCuenta === '' && strlen($cuenta) === 13) $tipoCuenta = 'C';

        if (!validarCuentaBcpPorTipo($cuenta, $tipoCuenta)) {
            throw new DomainException("{$descripcion} no tiene una cuenta BCP válida.");
        }
        if ((int)($registro['cuenta_bcp_verificada'] ?? 1) !== 1) {
            throw new DomainException("La cuenta BCP de {$descripcion} todavía no ha sido validada por Tesorería.");
        }
        $pago['cuenta_bcp'] = $cuenta;
        $pago['tipo_cuenta_bcp'] = $tipoCuenta;
        $pago['ref_beneficiario'] = $registro['ref_beneficiario'] ?? ('CAJA CHICA ' . $registro['dni']);
        $pago['ref_empresa'] = $registro['ref_empresa'] ?? ('REF ' . $registro['dni']);
    }

    return $pago;
}

function crearArchivoTxtBanco(array $empresa, array $pagos, string $prefijo, string $banco): array
{
    $banco = normalizarBancoPago($banco);
    $tz = new DateTimeZone('America/Lima');
    $selloFecha = (new DateTimeImmutable('now', $tz))->format('Ymd_His');

    if ($banco === 'BBVA') {
        $cuentaCargo = trim((string)($empresa['cuenta_cargo_bbva'] ?? ''));
        if (!preg_match('/^\d{20}$/', $cuentaCargo)) {
            throw new DomainException('La empresa no tiene una cuenta de cargo BBVA válida.');
        }

        return [
            'contenido' => generarContenidoTxtBbva($cuentaCargo, $pagos),
            'nombre_archivo' => sprintf('%s_BBVA_%s.txt', $prefijo, $selloFecha),
        ];
    }

    $cuentaCargoBcp = trim((string)($empresa['cuenta_cargo_bcp'] ?? ''));
    $tipoCuentaCargoBcp = strtoupper(trim((string)($empresa['tipo_cuenta_cargo_bcp'] ?? 'C')));
    if (!preg_match('/^\d{13}$/', $cuentaCargoBcp)) {
        throw new DomainException('La empresa no tiene una cuenta de cargo BCP válida (13 dígitos).');
    }
    if (!in_array($tipoCuentaCargoBcp, ['C', 'M'], true)) {
        throw new DomainException('El tipo de cuenta de cargo BCP de la empresa no es válido.');
    }

    $referencia = $prefijo === 'DESEMBOLSO_FONDOS' ? 'DESEMBOLSO FONDOS CAJA CHICA' : 'REEMBOLSO CAJA CHICA';
    return [
        'contenido' => generarContenidoTxtBcp($cuentaCargoBcp, $tipoCuentaCargoBcp, $pagos, $referencia),
        'nombre_archivo' => sprintf('%s_BCP_%s.txt', $prefijo, $selloFecha),
    ];
}

function crearArchivoTxtBbva(array $empresa, array $pagos, string $prefijo): array
{
    return crearArchivoTxtBanco($empresa, $pagos, $prefijo, 'BBVA');
}

function guardarCuentaEmpresaBbva(
    int $empresaId,
    string $cuenta,
    string $confirmacionCuenta
): array {
    $cuenta = preg_replace('/\D/', '', $cuenta) ?? '';
    $confirmacionCuenta = preg_replace('/\D/', '', $confirmacionCuenta) ?? '';

    if ($empresaId <= 0) return ['ok' => false, 'error' => 'Selecciona una empresa.'];
    if (!preg_match('/^\d{20}$/', $cuenta)) return ['ok' => false, 'error' => 'La cuenta de cargo BBVA debe contener exactamente 20 dígitos.'];
    if ($cuenta !== $confirmacionCuenta) return ['ok' => false, 'error' => 'Las cuentas ingresadas no coinciden.'];
    if (!obtenerEmpresaPagoBbva($empresaId)) return ['ok' => false, 'error' => 'Empresa no encontrada.'];

    actualizarCuentaCargoBbva($empresaId, $cuenta);
    return ['ok' => true];
}

function guardarCuentaEmpresaBcp(
    int $empresaId,
    string $cuenta,
    string $confirmacionCuenta,
    string $tipoCuenta = 'C'
): array {
    $cuenta = preg_replace('/\D/', '', $cuenta) ?? '';
    $confirmacionCuenta = preg_replace('/\D/', '', $confirmacionCuenta) ?? '';
    $tipoCuenta = strtoupper(trim($tipoCuenta));

    if ($empresaId <= 0) return ['ok' => false, 'error' => 'Selecciona una empresa.'];
    if (!in_array($tipoCuenta, ['C', 'M'], true)) return ['ok' => false, 'error' => 'Selecciona Corriente (C) o Maestra (M).'];
    if (!preg_match('/^\d{13}$/', $cuenta)) return ['ok' => false, 'error' => 'La cuenta de cargo BCP debe contener exactamente 13 dígitos.'];
    if ($cuenta !== $confirmacionCuenta) return ['ok' => false, 'error' => 'Las cuentas BCP ingresadas no coinciden.'];
    if (!obtenerEmpresaPagoBbva($empresaId)) return ['ok' => false, 'error' => 'Empresa no encontrada.'];

    actualizarCuentaCargoBcp($empresaId, $cuenta, $tipoCuenta);
    return ['ok' => true];
}

function guardarCuentaEmpresaBanco(
    int $empresaId,
    string $banco,
    string $cuenta,
    string $confirmacionCuenta,
    string $tipoCuenta = 'C'
): array {
    $banco = strtoupper(trim($banco));
    if ($banco === 'BCP') {
        return guardarCuentaEmpresaBcp($empresaId, $cuenta, $confirmacionCuenta, $tipoCuenta);
    }
    if ($banco === 'BBVA') {
        return guardarCuentaEmpresaBbva($empresaId, $cuenta, $confirmacionCuenta);
    }
    return ['ok' => false, 'error' => 'Banco no válido.'];
}

function validarCuentaTrabajadorTesoreria(int $trabajadorId, string $banco): array
{
    $banco = strtoupper(trim($banco));
    if ($trabajadorId <= 0 || !in_array($banco, ['BBVA', 'BCP'], true)) {
        return ['ok' => false, 'error' => 'Datos de cuenta inválidos.'];
    }

    $actualizado = validarCuentaTrabajadorDb($trabajadorId, $banco);
    if (!$actualizado) {
        return ['ok' => false, 'error' => 'La cuenta ya estaba validada o no existe.'];
    }

    return [
        'ok' => true,
        'mensaje' => "Cuenta {$banco} validada correctamente.",
        'pendientes_validacion' => contarCuentasPendientesValidacion(),
    ];
}

function obtenerEstadoPagosBbva(int $empresaId, bool $incluirCuentasPendientes = false): array
{
    $empresa = obtenerEmpresaPagoBbva($empresaId);
    if (!$empresa) return ['ok' => false, 'error' => 'Empresa no encontrada.'];

    $pagos = listarCajasPagosBbva($empresaId);

    foreach ($pagos as &$caja) {
        $caja['descargada'] = (int)$caja['txt_descargado'] === 1;
        $bancoParaMostrar = $caja['descargada'] ? ($caja['txt_banco'] ?: 'BBVA') : null;
        $eval = evaluarCuentaDestinoRegistro($caja, $bancoParaMostrar);
        $importeValido = decimalToCentimosBbva((string)$caja['importe']) > 0;

        $caja['banco_destino'] = $eval['banco_destino'];
        $caja['banco_pago'] = $caja['descargada'] ? ($caja['txt_banco'] ?: 'BBVA') : null;
        $caja['cuenta_enmascarada'] = $eval['cuenta_enmascarada'];
        $caja['tipo_cuenta_etiqueta'] = $eval['tipo_cuenta_etiqueta'];
        $caja['cuenta_verificada'] = $eval['cuenta_verificada'];
        $caja['habilitada'] = !$caja['descargada'] && $eval['cuenta_valida'] && $importeValido;

        if ($caja['descargada']) $caja['motivo_invalido'] = 'TXT descargado';
        elseif (!$eval['cuenta_valida']) $caja['motivo_invalido'] = $eval['motivo_cuenta'];
        elseif (!$importeValido) $caja['motivo_invalido'] = 'El importe debe ser mayor que cero.';
        else $caja['motivo_invalido'] = null;

        unset($caja['cuenta_bbva'], $caja['cuenta_bcp']);
    }
    unset($caja);

    $fondos = listarFondosDesembolsoBbva($empresaId);
    foreach ($fondos as &$fondo) {
        $desembolsado = $fondo['estado'] !== 'PENDIENTE_DESEMBOLSO';
        $bancoParaMostrar = $desembolsado ? ($fondo['banco_desembolso'] ?: 'BBVA') : null;
        $eval = evaluarCuentaDestinoRegistro($fondo, $bancoParaMostrar);
        $importeValido = decimalToCentimosBbva((string)$fondo['importe']) > 0;

        $fondo['banco_destino'] = $eval['banco_destino'];
        $fondo['banco_pago'] = $desembolsado ? ($fondo['banco_desembolso'] ?: 'BBVA') : null;
        $fondo['cuenta_enmascarada'] = $eval['cuenta_enmascarada'];
        $fondo['tipo_cuenta_etiqueta'] = $eval['tipo_cuenta_etiqueta'];
        $fondo['cuenta_verificada'] = $eval['cuenta_verificada'];
        $fondo['habilitada'] = !$desembolsado && $eval['cuenta_valida'] && $importeValido;
        $fondo['motivo_invalido'] = $fondo['habilitada']
            ? null
            : ($desembolsado
                ? 'TXT generado'
                : (!$eval['cuenta_valida']
                    ? $eval['motivo_cuenta']
                    : 'El importe debe ser mayor que cero.'));

        unset($fondo['cuenta_bbva'], $fondo['cuenta_bcp']);
    }
    unset($fondo);

    $empresa['cuenta_enmascarada'] = enmascararCuentaBbva($empresa['cuenta_cargo_bbva'] ?? null);
    $empresa['cuenta_enmascarada_bbva'] = $empresa['cuenta_enmascarada'];
    $empresa['cuenta_enmascarada_bcp'] = enmascararCuentaBbva($empresa['cuenta_cargo_bcp'] ?? null);
    $empresa['tipo_cuenta_cargo_bcp'] = $empresa['tipo_cuenta_cargo_bcp'] ?: 'C';

    $tz = new DateTimeZone('America/Lima');
    $fechaHoyLima = (new DateTimeImmutable('now', $tz))->format('d/m/Y');

    return [
        'ok' => true,
        'empresa' => $empresa,
        'pagos' => $pagos,
        'fondos' => $fondos,
        'cuentas_pendientes_validacion' => $incluirCuentasPendientes ? listarCuentasPendientesValidacion() : [],
        'fecha_hoy_lima' => $fechaHoyLima,
    ];
}

function normalizarIdsBbva(array $ids): array
{
    return array_values(array_unique(array_filter(
        array_map('intval', $ids),
        fn(int $id): bool => $id > 0
    )));
}

function prepararPagosCajasBbva(int $empresaId, array $cajasIds, string $banco = 'BBVA'): array
{
    $cajas = bloquearCajasPagosBbva($cajasIds);
    if (count($cajas) !== count($cajasIds)) {
        throw new DomainException('Una o más cajas seleccionadas no existen.');
    }

    $pagos = [];
    foreach ($cajas as $caja) {
        if ((int)$caja['empresa_id'] !== $empresaId) {
            throw new DomainException('Todas las cajas deben pertenecer a la misma empresa.');
        }
        if ($caja['estado_general'] !== 'VISADO') {
            throw new DomainException("La caja {$caja['numero']} todavía no está visada.");
        }
        if ((int)$caja['txt_descargado'] === 1) {
            throw new DomainException("La caja {$caja['numero']} ya fue descargada.");
        }

        $numPad = str_pad((string)$caja['numero'], 3, '0', STR_PAD_LEFT);
        $caja['ref_beneficiario'] = "CAJA CHICA N {$numPad} {$caja['dni']}";
        $caja['ref_empresa'] = "CAJA {$numPad} {$caja['dni']}";
        $pagos[] = construirPagoBbva($caja, "el trabajador de la caja {$caja['numero']}", $banco);
    }

    return $pagos;
}

function prepararPagosFondosBbva(int $empresaId, array $fondosIds, string $banco = 'BBVA'): array
{
    $fondos = bloquearFondosDesembolsoBbva($fondosIds);
    if (count($fondos) !== count($fondosIds)) {
        throw new DomainException('Uno o más fondos no existen.');
    }

    $pagos = [];
    foreach ($fondos as $fondo) {
        if ((int)$fondo['empresa_id'] !== $empresaId) {
            throw new DomainException('Todos los fondos deben pertenecer a la misma empresa.');
        }
        if ($fondo['estado'] !== 'PENDIENTE_DESEMBOLSO') {
            throw new DomainException("El fondo {$fondo['id']} ya fue desembolsado.");
        }

        $fondo['ref_beneficiario'] = "FONDO CAJA CHICA {$fondo['dni']}";
        $fondo['ref_empresa'] = "FONDO {$fondo['id']} {$fondo['dni']}";
        $pagos[] = construirPagoBbva($fondo, "el fondo de {$fondo['trabajador']}", $banco);
    }

    return $pagos;
}

function generarTxtPagosBbva(
    int $empresaId,
    array $ids,
    string $origen,
    int $usuarioId = 0,
    string $banco = 'BBVA'
): array {
    $pdo = obtenerConexion();
    $ids = normalizarIdsBbva($ids);

    if ($empresaId <= 0 || $ids === [] || !in_array($origen, ['caja', 'fondo'], true)) {
        return ['ok' => false, 'error' => 'Selecciona una empresa y al menos un pago válido.'];
    }

    try {
        $banco = normalizarBancoPago($banco);
        $pdo->beginTransaction();
        $empresa = obtenerEmpresaPagoBbva($empresaId, true);
        if (!$empresa) throw new DomainException('Empresa no encontrada.');

        $pagos = $origen === 'caja'
            ? prepararPagosCajasBbva($empresaId, $ids, $banco)
            : prepararPagosFondosBbva($empresaId, $ids, $banco);
        $prefijo = $origen === 'caja' ? 'PAGO_CAJA_CHICA' : 'DESEMBOLSO_FONDOS';
        $archivoTxt = crearArchivoTxtBanco($empresa, $pagos, $prefijo, $banco);

        require_once __DIR__ . '/generarReporteDesembolsoPdf.php';
        $tz = new DateTimeZone('America/Lima');
        $ahoraLima = new DateTimeImmutable('now', $tz);
        $fechaFin = $ahoraLima->format('Y-m-d');
        if ($origen === 'caja') {
            $fechasVisado = array_filter(array_column($pagos, 'fecha_visado_tesoreria'));
            $fechaInicio = $fechasVisado ? min($fechasVisado) : $fechaFin;
            $tipoDesembolso = 'REEMBOLSO';
        } else {
            $fechasAsignacion = array_filter(array_column($pagos, 'fecha_asignacion'));
            $fechaInicio = $fechasAsignacion ? min($fechasAsignacion) : $fechaFin;
            $tipoDesembolso = 'ASIGNACIÓN DE FONDO';
        }

        $contenidoPdf = generarReporteDesembolsoPdf([
            'empresa' => $empresa,
            'pagos' => $pagos,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'tipoDesembolso' => $tipoDesembolso,
            'banco' => $banco,
        ]);

        $nombrePdf = sprintf('REPORTE_%s_%s_%s.pdf', $prefijo, $banco, $ahoraLima->format('Ymd_His'));

        if ($origen === 'caja') {
            marcarTxtDescargado($ids, $banco);
            foreach ($ids as $cajaId) {
                registrarEventoResumen(
                    $cajaId,
                    'TXT_DESCARGADO',
                    $usuarioId,
                    null,
                    "Descarga de txt {$banco} registrada."
                );
            }
        } else {
            marcarFondosDesembolsados($ids, $banco);
        }

        $pdo->commit();

        return [
            'ok' => true,
            'txt' => [
                'contenido' => base64_encode($archivoTxt['contenido']),
                'nombre_archivo' => $archivoTxt['nombre_archivo'],
            ],
            'pdf' => [
                'contenido' => base64_encode($contenidoPdf),
                'nombre_archivo' => $nombrePdf,
            ],
        ];
    } catch (DomainException | InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        return ['ok' => false, 'error' => 'No se pudo generar el archivo.'];
    }
}

function generarTxtBbva(int $empresaId, array $cajasIds, int $usuarioId, string $banco = 'BBVA'): array
{
    return generarTxtPagosBbva($empresaId, $cajasIds, 'caja', $usuarioId, $banco);
}

function generarTxtDesembolsoFondosBbva(int $empresaId, array $fondosIds, string $banco = 'BBVA'): array
{
    return generarTxtPagosBbva($empresaId, $fondosIds, 'fondo', 0, $banco);
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
            if ((int)$registro['empresa_id'] !== $empresaId) {
                throw new DomainException('Todos los pagos deben pertenecer a la empresa seleccionada.');
            }
            if ($origen === 'caja') {
                if ((int)$registro['txt_descargado'] !== 1) {
                    throw new DomainException("La caja {$registro['numero']} ya está habilitada.");
                }
            } else {
                if ($registro['estado'] !== 'ACTIVO' || !$registro['fecha_desembolso']) {
                    throw new DomainException("El fondo {$registro['id']} no tiene un desembolso registrado.");
                }
                if ((int)$registro['tiene_cajas'] === 1) {
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
