<?php

function normalizarTextoBcp(string $texto): string
{
    $texto = trim($texto);
    // Reemplazo explícito antes de iconv para no depender del locale del servidor Linux/cPanel.
    $mapa = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ý' => 'y',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N', 'Ý' => 'Y',
    ];
    $texto = strtr($texto, $mapa);

    $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    if ($convertido !== false) {
        $texto = $convertido;
    }

    $texto = strtoupper($texto);
    $texto = preg_replace('/[^A-Z0-9 .\-]/', '', $texto) ?? '';
    return trim(preg_replace('/\s+/', ' ', $texto) ?? '');
}

function campoTextoBcp(string $valor, int $longitud, string $respaldo = 'CAJA CHICA'): string
{
    $valor = normalizarTextoBcp($valor);
    if ($valor === '') {
        $valor = normalizarTextoBcp($respaldo);
    }
    $valor = substr($valor, 0, $longitud);
    return str_pad($valor, $longitud, ' ', STR_PAD_RIGHT);
}

function campoNumericoBcp(string|int $valor, int $longitud): string
{
    $valor = trim((string)$valor);
    if (!preg_match('/^\d+$/', $valor)) {
        throw new InvalidArgumentException('El campo BCP debe contener solamente números.');
    }
    if (strlen($valor) > $longitud) {
        throw new InvalidArgumentException('Un valor numérico excede la longitud permitida por BCP.');
    }
    return str_pad($valor, $longitud, '0', STR_PAD_LEFT);
}

function formatearImporteBcp(int $importeCentimos): string
{
    if ($importeCentimos <= 0) {
        throw new InvalidArgumentException('El importe BCP debe ser mayor que cero.');
    }
    $enteros = intdiv($importeCentimos, 100);
    $decimales = $importeCentimos % 100;
    $texto = sprintf('%d.%02d', $enteros, $decimales);
    if (strlen($texto) > 17) {
        throw new InvalidArgumentException('El importe excede el límite permitido por BCP.');
    }
    return str_pad($texto, 17, '0', STR_PAD_LEFT);
}

function validarCuentaBcpPorTipo(string $cuenta, string $tipoCuenta): bool
{
    return match ($tipoCuenta) {
        'A' => preg_match('/^\d{14}$/', $cuenta) === 1,
        'C', 'M' => preg_match('/^\d{13}$/', $cuenta) === 1,
        'B' => preg_match('/^\d{20}$/', $cuenta) === 1,
        default => false,
    };
}

function sumarEnterosGrandes(string $a, string $b): string
{
    $a = ltrim($a, '0') ?: '0';
    $b = ltrim($b, '0') ?: '0';
    $i = strlen($a) - 1;
    $j = strlen($b) - 1;
    $carry = 0;
    $res = '';
    while ($i >= 0 || $j >= 0 || $carry > 0) {
        $da = $i >= 0 ? (int)$a[$i--] : 0;
        $db = $j >= 0 ? (int)$b[$j--] : 0;
        $suma = $da + $db + $carry;
        $res = ($suma % 10) . $res;
        $carry = intdiv($suma, 10);
    }
    return $res;
}

function extraerComponenteChecksumBcp(string $cuenta, string $tipoCuenta): string
{
    if (!validarCuentaBcpPorTipo($cuenta, $tipoCuenta)) {
        throw new InvalidArgumentException('La cuenta BCP no coincide con la longitud esperada para su tipo.');
    }
    $recortada = $tipoCuenta === 'B' ? substr($cuenta, 10) : substr($cuenta, 3);
    return ltrim($recortada, '0') ?: '0';
}

function calcularChecksumBcp(string $cuentaCargo, string $tipoCuentaCargo, array $pagos): string
{
    $acumulado = extraerComponenteChecksumBcp($cuentaCargo, $tipoCuentaCargo);
    foreach ($pagos as $pago) {
        $cuentaAbono = trim((string)($pago['cuenta_bcp'] ?? ''));
        $tipoAbono = strtoupper(trim((string)($pago['tipo_cuenta_bcp'] ?? '')));
        $acumulado = sumarEnterosGrandes($acumulado, extraerComponenteChecksumBcp($cuentaAbono, $tipoAbono));
    }
    return campoNumericoBcp($acumulado, 15);
}

function obtenerFechaProcesoBcp(?string $fechaYmd = null): string
{
    $config = require __DIR__ . '/../../config/bcp.php';
    if ($fechaYmd !== null) {
        if (!preg_match('/^\d{8}$/', $fechaYmd)) {
            throw new InvalidArgumentException('La fecha de proceso BCP debe tener formato AAAAMMDD.');
        }
        return $fechaYmd;
    }
    $tz = new DateTimeZone($config['zona_horaria'] ?? 'America/Lima');
    return (new DateTimeImmutable('now', $tz))->format('Ymd');
}

function construirCabeceraBcp(
    string $cuentaCargo,
    string $tipoCuentaCargo,
    int $importeCentimos,
    int $cantidadDetalles,
    string $checksum,
    string $referenciaPlanilla = 'CAJA CHICA',
    ?string $fechaProceso = null
): string {
    $config = require __DIR__ . '/../../config/bcp.php';
    $cuentaCargo = trim($cuentaCargo);
    $tipoCuentaCargo = strtoupper(trim($tipoCuentaCargo));

    if (!in_array($tipoCuentaCargo, ['C', 'M'], true)) {
        throw new InvalidArgumentException('El tipo de cuenta de cargo BCP debe ser Corriente (C) o Maestra (M).');
    }
    if (!preg_match('/^\d{13}$/', $cuentaCargo)) {
        throw new InvalidArgumentException('La cuenta de cargo BCP debe tener exactamente 13 dígitos.');
    }
    if ($cantidadDetalles <= 0) {
        throw new InvalidArgumentException('La planilla BCP debe contener al menos un abono.');
    }

    $fecha = obtenerFechaProcesoBcp($fechaProceso);

    $cabecera =
        $config['tipo_registro_cabecera']
        . campoNumericoBcp($cantidadDetalles, 6)
        . $fecha
        . $config['subtipo_planilla']
        . $tipoCuentaCargo
        . $config['moneda']
        . str_pad($cuentaCargo, 20, ' ', STR_PAD_RIGHT)
        . formatearImporteBcp($importeCentimos)
        . campoTextoBcp($referenciaPlanilla, 40, 'CAJA CHICA')
        . campoNumericoBcp($checksum, 15);

    if (strlen($cabecera) !== 113) {
        throw new RuntimeException('La cabecera BCP debe tener exactamente 113 caracteres.');
    }

    return $cabecera;
}

function construirDetalleBcp(array $pago): string
{
    $config = require __DIR__ . '/../../config/bcp.php';

    $dni = trim((string)($pago['dni'] ?? ''));
    $cuenta = trim((string)($pago['cuenta_bcp'] ?? ''));
    $tipoCuenta = strtoupper(trim((string)($pago['tipo_cuenta_bcp'] ?? '')));
    $importeCentimos = (int)($pago['importe_centimos'] ?? 0);

    if (!preg_match('/^\d{8}$/', $dni)) {
        throw new InvalidArgumentException("El DNI {$dni} debe tener 8 dígitos.");
    }
    if (!validarCuentaBcpPorTipo($cuenta, $tipoCuenta)) {
        throw new InvalidArgumentException("La cuenta BCP correspondiente al DNI {$dni} no es válida.");
    }
    if ($importeCentimos <= 0) {
        throw new InvalidArgumentException("El importe correspondiente al DNI {$dni} no es válido.");
    }

    $refBenef = (string)($pago['ref_beneficiario'] ?? ('CAJA CHICA ' . $dni));
    $refEmp = (string)($pago['ref_empresa'] ?? ('REF ' . $dni));

    $detalle =
        $config['tipo_registro_detalle']
        . $tipoCuenta
        . str_pad($cuenta, 20, ' ', STR_PAD_RIGHT)
        . $config['tipo_documento']
        . str_pad($dni, 12, ' ', STR_PAD_RIGHT)
        . str_repeat(' ', 3)
        . campoTextoBcp((string)($pago['trabajador'] ?? ''), 75, 'BENEFICIARIO')
        . campoTextoBcp($refBenef, 40, 'CAJA CHICA')
        . campoTextoBcp($refEmp, 20, 'CAJA CHICA')
        . $config['moneda']
        . formatearImporteBcp($importeCentimos)
        . $config['flag_validar_idc'];

    if (strlen($detalle) !== 195) {
        throw new RuntimeException('Un detalle BCP no tiene exactamente 195 caracteres.');
    }

    return $detalle;
}

function generarContenidoTxtBcp(
    string $cuentaCargo,
    string $tipoCuentaCargo,
    array $pagos,
    string $referenciaPlanilla = 'CAJA CHICA',
    ?string $fechaProceso = null
): string {
    if ($pagos === []) {
        throw new InvalidArgumentException('Selecciona al menos un pago.');
    }

    $cuentaCargo = trim($cuentaCargo);
    $tipoCuentaCargo = strtoupper(trim($tipoCuentaCargo));
    $detalles = [];
    $totalCentimos = 0;

    foreach ($pagos as $pago) {
        $cuentaAbono = trim((string)($pago['cuenta_bcp'] ?? ''));
        if ($cuentaAbono === $cuentaCargo) {
            throw new InvalidArgumentException('La cuenta de abono no puede ser igual a la cuenta de cargo de la empresa.');
        }
        $totalCentimos += (int)$pago['importe_centimos'];
        $detalles[] = construirDetalleBcp($pago);
    }

    $checksum = calcularChecksumBcp($cuentaCargo, $tipoCuentaCargo, $pagos);
    $cabecera = construirCabeceraBcp(
        $cuentaCargo,
        $tipoCuentaCargo,
        $totalCentimos,
        count($detalles),
        $checksum,
        $referenciaPlanilla,
        $fechaProceso
    );

    return implode("\r\n", [$cabecera, ...$detalles]);
}
