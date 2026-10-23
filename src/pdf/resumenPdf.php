<?php
require_once __DIR__ . '/../fpdf/fpdf.php';
require_once __DIR__ . '/../fpdi/src/autoload.php';

use setasign\Fpdi\Fpdi;

/**
 * FPDF con fuentes base trabaja con Windows-1252, mientras que el sistema y
 * MySQL manejan UTF-8. La conversión pertenece únicamente a esta frontera.
 */
function textoPdf(?string $texto): string
{
    $texto = (string)$texto;
    $convertido = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $texto);

    return $convertido !== false ? $convertido : $texto;
}

class ResumenPdf extends Fpdi
{
    private string $tituloActual = '';
    private string $logoArchivo = 'logoGrupoTS.png';
    private string $rucEmpresa = '';
    private bool $siguientePaginaEsAnexo = false;
    private bool $paginaActualEsAnexo = false;

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4');
        $this->SetMargins(12, 12, 12);
        $this->SetAutoPageBreak(true, 20);
    }

    public function nuevaPagina(string $titulo): void
    {
        $this->tituloActual = $titulo;
        $this->siguientePaginaEsAnexo = false;
        $this->AddPage();
    }

    public function establecerLogo(?string $nombreArchivo): void
    {
        if ($nombreArchivo !== null && is_file(__DIR__ . '/../fpdf/' . $nombreArchivo)) {
            $this->logoArchivo = $nombreArchivo;
        }
    }

    public function establecerRucEmpresa(string $ruc): void
    {
        $ruc = preg_replace('/\D/', '', $ruc) ?? '';
        $this->rucEmpresa = strlen($ruc) === 11 ? $ruc : '';
    }

    function Header()
    {
        $this->paginaActualEsAnexo = $this->siguientePaginaEsAnexo;
        if ($this->paginaActualEsAnexo) {
            return;
        }

        $rutaLogo = __DIR__ . '/../fpdf/' . $this->logoArchivo;
        $xLogo = 12;
        $yLogo = 10;
        $anchoMax = 32;
        $altoMax = 16;
        [$anchoPx, $altoPx] = getimagesize($rutaLogo);
        $escala = min($anchoMax / $anchoPx, $altoMax / $altoPx);
        $anchoLogoReal = $anchoPx * $escala;
        $altoLogoReal = $altoPx * $escala;
        $this->Image($rutaLogo, $xLogo + ($anchoMax - $anchoLogoReal) / 2, $yLogo, $anchoLogoReal, $altoLogoReal);

        $this->SetXY($xLogo, $yLogo + $altoMax + 2);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($anchoMax, 4, 'RUC: ' . ($this->rucEmpresa !== '' ? $this->rucEmpresa : '-'), 0, 0, 'L');

        $this->SetXY($xLogo, $yLogo + $altoMax + 6);
        $this->SetFont('Arial', '', 6.5);
        $this->MultiCell($anchoMax, 3, textoPdf('UB: MZA. K LOTE 03 URB. NATASHA ALTA LA LIBERTAD - TRUJILLO - TRUJILLO'), 0, 'C');

        $xTitulo = $xLogo + $anchoMax + 6;
        $anchoTitulo = 210 - 12 - $xTitulo; // 210 = ancho A4, 12 = margen derecho
        $altoTitulo = 18;
        $this->SetXY($xTitulo, $yLogo);
        $this->SetFillColor(210, 210, 210);
        $this->SetFont('Arial', 'B', 15);
        $this->Cell($anchoTitulo, $altoTitulo, textoPdf($this->tituloActual), 1, 0, 'C', true);

        $yFinCabecera = max($yLogo + $altoMax + 12, $yLogo + $altoTitulo) + 4;
        $this->SetXY($xTitulo, $yFinCabecera);
        $this->SetFont('Arial', '', 9);
        $this->Cell($anchoTitulo, -15, 'Expresado en soles', 0, 0, 'C');
        $this->SetY($yFinCabecera + 8);
        $this->Ln(2);
    }

    function Footer()
    {
        if ($this->paginaActualEsAnexo) {
            return;
        }

        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 8, textoPdf('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C',);
    }

    public function datosGenerales(array $filasIzquierda, array $filasDerecha): void
    {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 6, '1' . chr(176) . ' DATOS GENERALES', 0, 1);
        $this->Ln(1);

        $anchoColumna = ($this->w - $this->lMargin - $this->rMargin - 8) / 2;
        $anchoEtiqueta = 45;
        $anchoValor = $anchoColumna - $anchoEtiqueta;
        $filas = max(count($filasIzquierda), count($filasDerecha));
        for ($i = 0; $i < $filas; $i++) {
            $celdas = [];
            $alto = 6;
            foreach ([$filasIzquierda[$i] ?? null, $filasDerecha[$i] ?? null] as $fila) {
                $this->SetFont('Arial', 'B', 9);
                $etiqueta = $this->lineasTexto((string)($fila[0] ?? ''), $anchoEtiqueta);
                $this->SetFont('Arial', '', 9);
                $valor = $this->lineasTexto((string)($fila[1] ?? ''), $anchoValor);
                $alto = max($alto, max(count($etiqueta), count($valor)) * 4.2 + 1.6);
                $celdas[] = [$etiqueta, $valor, $fila !== null];
            }
            if ($this->GetY() + $alto > $this->PageBreakTrigger) $this->AddPage();
            $y = $this->GetY();
            foreach ($celdas as $lado => [$etiqueta, $valor, $existe]) {
                if (!$existe) continue;
                $x = $this->lMargin + $lado * ($anchoColumna + 8);
                $this->SetFont('Arial', 'B', 9);
                $this->escribirLineas($x, $y, $anchoEtiqueta, $alto, $etiqueta, 4.2);
                $this->SetFont('Arial', '', 9);
                $this->escribirLineas($x + $anchoEtiqueta, $y, $anchoValor, $alto, $valor, 4.2);
                $this->Line($x + $anchoEtiqueta, $y + $alto, $x + $anchoColumna, $y + $alto);
            }
            $this->SetY($y + $alto);
        }
        $this->Ln(4);
    }

    public function tituloSeccion(string $texto): void
    {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 6, textoPdf($texto), 0, 1);
        $this->Ln(1);
    }

    public function firmas(array $firmas): void
    {
        if (!$firmas) return;
        $margen = $this->lMargin;
        $anchoDisponible = $this->w - $margen - $this->rMargin;
        $anchoCasilla = $anchoDisponible / count($firmas);
        $anchoInterior = $anchoCasilla - 3;
        $etiquetas = [];
        $nombres = [];
        foreach ($firmas as $firma) {
            $this->SetFont('Arial', 'B', 6.5);
            $etiquetas[] = $this->lineasTexto($firma['etiqueta'], $anchoInterior);
            $this->SetFont('Arial', '', 6);
            $nombres[] = $this->lineasTexto($firma['nombre'] ?? '', $anchoInterior - 2);
        }
        $altoEtiquetas = max(array_map('count', $etiquetas)) * 3.5;
        $altoNombres = max(array_map('count', $nombres)) * 3;
        $altoCaja = 18 + $altoNombres + 8;
        $altoBloque = 8 + $altoEtiquetas + 2 + $altoCaja + 4;
        if ($this->GetY() + $altoBloque > $this->PageBreakTrigger) $this->AddPage();

        $this->Ln(8);
        $yInicial = $this->GetY();
        $yCaja = $yInicial + $altoEtiquetas + 2;

        foreach ($firmas as $indice => $firma) {
            $x = $margen + ($indice * $anchoCasilla);
            $this->SetFont('Arial', 'B', 6.5);
            $this->escribirLineas($x, $yInicial, $anchoInterior, $altoEtiquetas, $etiquetas[$indice], 3.5, 'C');

            $this->Rect(
                $x,
                $yCaja,
                $anchoInterior,
                $altoCaja
            );
            if (!empty($firma['imagen']) && is_file($firma['imagen'])) {
                $dimensiones = @getimagesize($firma['imagen']);

                if ($dimensiones) {
                    [$anchoPx, $altoPx] = $dimensiones;
                    $anchoMaximo = $anchoInterior - 4;
                    $altoMaximo = 14;

                    $escala = min(
                        $anchoMaximo / $anchoPx,
                        $altoMaximo / $altoPx
                    );

                    $anchoImagen = $anchoPx * $escala;
                    $altoImagen = $altoPx * $escala;

                    $xImagen = $x + (($anchoInterior - $anchoImagen) / 2);
                    $yImagen = $yCaja + 2 + (($altoMaximo - $altoImagen) / 2);

                    $this->Image(
                        $firma['imagen'],
                        $xImagen,
                        $yImagen,
                        $anchoImagen,
                        $altoImagen
                    );
                }
            } elseif (!empty($firma['nombre'])) {
                $this->SetXY($x + 1, $yCaja + 7);
                $this->SetFont('Arial', 'I', 5);
                $this->Cell(
                    $anchoInterior - 2,
                    3,
                    'SIN FIRMA CARGADA',
                    0,
                    0,
                    'C'
                );
            }

            $this->SetFont('Arial', '', 6);
            $this->escribirLineas($x + 1, $yCaja + 18, $anchoInterior - 2, $altoNombres, $nombres[$indice], 3, 'C');

            $this->SetXY($x + 1, $yCaja + 18 + $altoNombres + 1);
            $this->SetFont('Arial', '', 5);
            $this->Cell(
                $anchoInterior - 2,
                3,
                $firma['fecha'] ?? '',
                0,
                0,
                'C'
            );

            if (!empty($firma['automatico'])) {
                $this->SetXY($x + 1, $yCaja + 18 + $altoNombres + 4);
                $this->SetFont('Arial', 'I', 4.7);
                $this->Cell(
                    $anchoInterior - 2,
                    3,
                    textoPdf('Validación automática'),
                    0,
                    0,
                    'C'
                );
            }
        }

        $this->SetY($yCaja + $altoCaja + 4);
    }

    public function tabla(array $columnas, array $filas, ?array $filaTotal = null): void
    {
        $altoLinea = 3.8;
        $prepararFila = function (array $fila) use ($columnas): array {
            $lineas = [];
            foreach ($columnas as $i => $columna) {
                $lineas[] = $this->lineasTexto((string)($fila[$i] ?? ''), $columna['ancho']);
            }
            return $lineas;
        };
        $dibujarFila = function (array $lineas, bool $relleno = false) use ($columnas, $altoLinea): void {
            $alto = max(6, max(array_map('count', $lineas)) * $altoLinea + 2);
            $x = $this->lMargin;
            $y = $this->GetY();
            foreach ($columnas as $i => $columna) {
                $this->Rect($x, $y, $columna['ancho'], $alto, $relleno ? 'DF' : 'D');
                $this->escribirLineas($x, $y, $columna['ancho'], $alto, $lineas[$i], $altoLinea, $columna['align'] ?? 'L');
                $x += $columna['ancho'];
            }
            $this->SetXY($this->lMargin, $y + $alto);
        };
        $dibujarCabecera = function () use ($columnas, $prepararFila, $dibujarFila): void {
            $this->SetFont('Arial', 'B', 7.5);
            $this->SetFillColor(230, 230, 230);
            $dibujarFila($prepararFila(array_column($columnas, 'titulo')), true);
        };

        if ($this->GetY() + 20 > $this->PageBreakTrigger) $this->AddPage();
        $dibujarCabecera();
        $indiceTotal = $filaTotal !== null ? count($filas) : -1;
        if ($filaTotal !== null) $filas[] = $filaTotal;
        foreach ($filas as $indice => $fila) {
            $estilo = $indice === $indiceTotal ? 'B' : '';
            $this->SetFont('Arial', $estilo, 7.5);
            $lineas = $prepararFila($fila);
            $cantidadLineas = max(array_map('count', $lineas));
            if ($this->GetY() + max(6, $cantidadLineas * $altoLinea + 2) > $this->PageBreakTrigger) {
                $this->AddPage();
                $dibujarCabecera();
                $this->SetFont('Arial', $estilo, 7.5);
            }
            // Una descripción excepcionalmente larga también puede continuar en otra página.
            for ($desde = 0; $desde < $cantidadLineas;) {
                $disponibles = max(1, (int)floor(($this->PageBreakTrigger - $this->GetY() - 2) / $altoLinea));
                $cantidad = min($cantidadLineas - $desde, $disponibles);
                $parte = array_map(fn(array $celda): array => array_slice($celda, $desde, $cantidad), $lineas);
                $dibujarFila($parte);
                $desde += $cantidad;
                if ($desde < $cantidadLineas) {
                    $this->AddPage();
                    $dibujarCabecera();
                    $this->SetFont('Arial', $estilo, 7.5);
                }
            }
        }
    }

    /** Divide por el ancho real de la fuente, incluso palabras sin espacios. */
    private function lineasTexto(string $texto, float $ancho): array
    {
        $texto = str_replace(["\r", "\t"], ['', ' '], textoPdf($texto));
        $texto = rtrim($texto, "\n");
        $limite = $ancho - 2 * $this->cMargin;
        $lineas = [];
        $inicio = $i = 0;
        $espacio = -1;
        $anchoLinea = 0;
        while ($i < strlen($texto)) {
            $caracter = $texto[$i];
            if ($caracter === "\n") {
                $lineas[] = substr($texto, $inicio, $i - $inicio);
                $inicio = ++$i;
                $espacio = -1;
                $anchoLinea = 0;
                continue;
            }
            if ($caracter === ' ') $espacio = $i;
            $anchoLinea += $this->GetStringWidth($caracter);
            if ($anchoLinea > $limite) {
                if ($espacio >= $inicio) {
                    $lineas[] = substr($texto, $inicio, $espacio - $inicio);
                    $i = $espacio + 1;
                } else {
                    if ($i === $inicio) $i++;
                    $lineas[] = substr($texto, $inicio, $i - $inicio);
                }
                $inicio = $i;
                $espacio = -1;
                $anchoLinea = 0;
            } else {
                $i++;
            }
        }
        $lineas[] = substr($texto, $inicio);
        return $lineas;
    }

    private function escribirLineas(float $x, float $y, float $ancho, float $alto, array $lineas, float $altoLinea, string $alineacion = 'L'): void
    {
        $y += ($alto - count($lineas) * $altoLinea) / 2;
        foreach ($lineas as $linea) {
            $this->SetXY($x, $y);
            $this->Cell($ancho, $altoLinea, $linea, 0, 0, $alineacion);
            $y += $altoLinea;
        }
    }

    // Incorpora las páginas originales sin rasterizar el PDF adjunto.
    public function anexarPdf(string $ruta): void
    {
        try {
            $cantidadPaginas = $this->setSourceFile($ruta);

            for ($pagina = 1; $pagina <= $cantidadPaginas; $pagina++) {
                $plantilla = $this->importPage($pagina);
                $tamano = $this->getTemplateSize($plantilla);

                $this->siguientePaginaEsAnexo = true;
                $this->AddPage(
                    $tamano['orientation'],
                    [$tamano['width'], $tamano['height']]
                );

                $this->useTemplate(
                    $plantilla,
                    0,
                    0,
                    $tamano['width'],
                    $tamano['height']
                );
            }
        } catch (Throwable $e) {
            throw new RuntimeException(
                'No se pudo incorporar el PDF adjunto: ' . basename($ruta)
            );
        }
    }

    /** Mantiene visibles imágenes antiguas que se cargaron antes de la regla PDF. */
    public function anexarImagenAntigua(string $ruta): void
    {
        $dimensiones = @getimagesize($ruta);
        if (!$dimensiones) {
            throw new RuntimeException('No se pudo leer la imagen adjunta: ' . basename($ruta));
        }

        [$anchoPx, $altoPx] = $dimensiones;
        $orientacion = $anchoPx > $altoPx ? 'L' : 'P';

        $this->siguientePaginaEsAnexo = true;
        $this->AddPage($orientacion, 'A4');

        $margen = 12;
        $anchoDisponible = $this->w - ($margen * 2);
        $altoDisponible = $this->h - ($margen * 2);
        $escala = min($anchoDisponible / $anchoPx, $altoDisponible / $altoPx);
        $ancho = $anchoPx * $escala;
        $alto = $altoPx * $escala;

        $this->Image($ruta, ($this->w - $ancho) / 2, ($this->h - $alto) / 2, $ancho, $alto);
    }
}
