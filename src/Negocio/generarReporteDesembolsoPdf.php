<?php
require_once __DIR__ . '/../pdf/resumenPdf.php';

function generarReporteDesembolsoPdf(array $data): string
{
    $empresa = $data['empresa'];
    $pagos = $data['pagos'];
    $fechaInicio = $data['fechaInicio'];
    $fechaFin = $data['fechaFin'];
    $tipoDesembolso = $data['tipoDesembolso'];

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetMargins(12, 12, 12);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    $periodo = datosPeriodoReporte($fechaInicio, $fechaFin);
    $margenIzq = 12;
    $margenDer = 198;
    $anchoCodigo = 22;
    $anchoNombre = 120;
    $anchoImporte = $margenDer - $margenIzq - $anchoCodigo - $anchoNombre;

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 5, textoPdf($empresa['razon_social'] ?? ''), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 4, textoPdf('RUC: ' . ($empresa['ruc'] ?? '-')), 0, 1, 'L');
    $pdf->Ln(4);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, textoPdf('REPORTE DE TRABAJADORES GENERADOS PARA BBVA'), 0, 1, 'C');
    $pdf->Ln(2);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(18, 5, 'MES', 0, 0, 'L');
    $pdf->Cell(50, 5, textoPdf($periodo['mes']), 0, 0, 'L');
    $pdf->Cell(0, 5, textoPdf($periodo['rango']), 0, 1, 'R');

    $campos = [
        'TIPO DESEMBOLSO' => $tipoDesembolso,
        'CLIENTE' => $empresa['razon_social'] ?? '-',
        'TIPO TRABAJADOR' => 'EMPLEADO',
        'ENTIDAD FINANCIERA' => 'BBVA',
    ];
    foreach ($campos as $etiqueta => $valor) {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(42, 5, textoPdf($etiqueta), 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, textoPdf($valor), 0, 1, 'L');
    }

    $y = $pdf->GetY() + 1;
    lineaPunteadaReporte($pdf, $y, $margenIzq, $margenDer);
    $pdf->SetY($y + 1);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($anchoCodigo, 5, 'CODIGO', 0, 0, 'L');
    $pdf->Cell($anchoNombre, 5, 'NOMBRES Y APELLIDOS', 0, 0, 'L');
    $pdf->Cell($anchoImporte, 5, 'NETO A PAGAR', 0, 1, 'R');

    $y = $pdf->GetY();
    lineaPunteadaReporte($pdf, $y, $margenIzq, $margenDer);
    $pdf->SetY($y + 1);

    $pdf->SetFont('Arial', '', 9);
    $totalImporte = 0;
    $count = 0;
    foreach ($pagos as $p) {
        $codigo = (string)($p['codigo_planilla'] ?? '-');
        $nombre = textoPdf(mb_strtoupper((string)($p['trabajador'] ?? $p['nombres'] ?? '-'), 'UTF-8'));
        $importe = ($p['importe_centimos'] ?? 0) / 100;
        $totalImporte += $importe;
        $count++;

        $pdf->Cell($anchoCodigo, 5, textoPdf($codigo), 0, 0, 'L');
        $pdf->Cell($anchoNombre, 5, $nombre, 0, 0, 'L');
        $pdf->Cell($anchoImporte, 5, number_format($importe, 2, '.', ''), 0, 1, 'R');
    }

    $y = $pdf->GetY();
    lineaPunteadaReporte($pdf, $y, $margenIzq, $margenDer);
    $pdf->SetY($y + 1);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($anchoCodigo + $anchoNombre, 5, 'Total Trabajadores:  ' . $count, 0, 0, 'L');
    $pdf->Cell($anchoImporte, 5, number_format($totalImporte, 2, '.', ''), 0, 1, 'R');

    $y = $pdf->GetY();
    lineaPunteadaReporte($pdf, $y, $margenIzq, $margenDer);

    $pdf->Ln(18);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($anchoCodigo + $anchoNombre, 5, 'BANCO CONTINENTAL', 0, 0, 'L');
    $pdf->Cell($anchoImporte, 5, number_format($totalImporte, 2, '.', ''), 0, 1, 'R');

    return $pdf->Output('S');
}

function datosPeriodoReporte(string $ini, string $fin): array
{
    $meses = [
        1 => 'ENERO',
        2 => 'FEBRERO',
        3 => 'MARZO',
        4 => 'ABRIL',
        5 => 'MAYO',
        6 => 'JUNIO',
        7 => 'JULIO',
        8 => 'AGOSTO',
        9 => 'SEPTIEMBRE',
        10 => 'OCTUBRE',
        11 => 'NOVIEMBRE',
        12 => 'DICIEMBRE',
    ];
    $inicio = new DateTime($ini);
    $fechaFin = new DateTime($fin);

    return [
        'mes' => $meses[(int)$inicio->format('n')],
        'rango' => 'Del ' . $inicio->format('d/m/y') . ' al ' . $fechaFin->format('d/m/y'),
    ];
}

function lineaPunteadaReporte(FPDF $pdf, float $y, float $desde, float $hasta): void
{
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.2);
    for ($x = $desde; $x < $hasta; $x += 1.6) {
        $pdf->Line($x, $y, min($x + 0.7, $hasta), $y);
    }
}
