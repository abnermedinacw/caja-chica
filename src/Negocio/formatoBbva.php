<?php

function normalizarTextoBbva(string $texto): string
{
    $texto = trim($texto);

    $convertido = iconv(
        'UTF-8',
        'ASCII//TRANSLIT//IGNORE',
        $texto
    );

    if ($convertido !== false) $texto = $convertido;

    $texto = strtoupper($texto);
    $texto = preg_replace('/[^A-Z0-9 .,\-]/', '', $texto);

    return preg_replace('/\s+/', ' ', $texto);
}

function campoTextoBbva(string $valor, int $longitud): string
{
    $valor = normalizarTextoBbva($valor);
    $valor = substr($valor, 0, $longitud);

    return str_pad($valor, $longitud, ' ', STR_PAD_RIGHT);
}

function campoNumericoBbva(string|int $valor, int $longitud): string
{
    $valor = trim((string)$valor);

    if (!preg_match('/^\d+$/', $valor)) throw new InvalidArgumentException('El campo BBVA debe contener solamente números.');
    
    if (strlen($valor) > $longitud) throw new InvalidArgumentException('Un valor numérico excede la longitud permitida por BBVA.');

    return str_pad($valor, $longitud, '0', STR_PAD_LEFT);
}

// en ingles porque si
function decimalToCentimosBbva(string $importe): int
{
    $importe = trim(str_replace(',', '.', $importe));

    if (!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $importe)) throw new InvalidArgumentException('El importe no es válido');

    [$enteros, $decimales] = array_pad(explode('.', $importe, 2), 2, '');

    $decimales = str_pad($decimales, 2, '0', STR_PAD_RIGHT);
    return ((int)$enteros * 100) + (int)$decimales;
}

function construirCabeceraBbva(
    string $cuentaCargo, 
    int $importeCentimos,
    int $cantidadDetalles
): string 
{
    $config = require __DIR__ . '/../../config/bbva.php';
    
    if (!preg_match('/^\d{20}$/', $cuentaCargo)) throw new InvalidArgumentException('La cuenta de cargo debe tener 20 dígitos.');

    if ($importeCentimos <= 0) throw new InvalidArgumentException('El importe total debe ser mayor que cero.');

    $cabecera = 
        $config['tipo_registro_cabecera']
        . $cuentaCargo
        . $config['moneda']
        . campoNumericoBbva($importeCentimos, 15)
        . $config['tipo_proceso']
        . str_repeat(' ', 8)
        . ' '
        . str_repeat(' ', 25)
        . campoNumericoBbva($cantidadDetalles, 6)
        . $config['validacion_pertenencia']
        . str_repeat('0', 15)
        . str_repeat('0', 3)
        . str_repeat(' ', 30)
        . str_repeat(' ', 15)
        . str_repeat(' ', 5);

    if (strlen($cabecera) !== 151) throw new RuntimeException('La cabecera BBVA debe tener 151 caracteres.');

    return $cabecera;
}

function construirDetalleBbva(array $pago): string
{
    $config = require __DIR__ . '/../../config/bbva.php';

    $dni = trim($pago['dni']);
    $cuenta = trim($pago['cuenta_bbva']);
    $importeCentimos = (int)$pago['importe_centimos'];

    if (!preg_match('/^\d{8}$/', $dni)) throw new InvalidArgumentException("El DNI {$dni} debe tener 8 dígitos");

    if (!preg_match('/^\d{20}$/', $cuenta)) throw new InvalidArgumentException("El pago correspondiente al DNI {$dni} no es válido.");

    if ($importeCentimos <= 0) throw new InvalidArgumentException("El pago correspondiente al DNI {$dni} no es válido.");

    $detalle = 
        $config['tipo_registro_detalle']
        . $config['tipo_documento']
        . str_pad($dni, 12, ' ', STR_PAD_RIGHT)
        . $config['tipo_abono']
        . $cuenta
        . campoTextoBbva($pago['trabajador'], 40)
        . campoNumericoBbva($importeCentimos, 15)
        . str_repeat(' ', 40)
        . ' '
        . str_repeat(' ', 50)
        . str_repeat(' ', 50);
    
        if (strlen($detalle) !== 233) throw new RuntimeException('Un detalle BBVA no tiene 233 caracteres.');

        return $detalle;
}

function generarContenidoTxtBbva(
    string $cuentaCargo,
    array $pagos
): string
{
    if ($pagos === []) throw new InvalidArgumentException('Selecciona al menos una caja.');

    $detalles = [];
    $totalCentimos = 0;

    foreach ($pagos as $pago) {
        $totalCentimos += (int)$pago['importe_centimos'];
        $detalles[] = construirDetalleBbva($pago);
    }

    $cabecera = construirCabeceraBbva(
        $cuentaCargo,
        $totalCentimos,
        count($detalles)
    );

    return implode("\r\n", [$cabecera, ...$detalles]);
}