<?php
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/../src/pdf/resumenPdf.php';

class PdfDePrueba extends ResumenPdf
{
    public int $celdas = 0;

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        if ($txt !== '' && $w > 0 && $this->GetStringWidth($txt) > $w - 2 * $this->cMargin + 0.05) {
            throw new RuntimeException('Texto fuera de su celda: ' . iconv('Windows-1252', 'UTF-8', $txt));
        }
        if ($h > 0 && $this->GetY() + $h > $this->PageBreakTrigger + 0.05 && $this->GetY() < $this->h - 20) {
            throw new RuntimeException('Una celda invade el pie de página.');
        }
        $this->celdas++;
        parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);
    }
}

$carpeta = __DIR__ . '/../tmp/pdfs';
if (!is_dir($carpeta)) mkdir($carpeta, 0775, true);
$pdf = new PdfDePrueba();
$pdf->AliasNbPages();
$pdf->establecerRucEmpresa('20123456789');
$pdf->nuevaPagina('SUSTENTO DOCUMENTOS NO CONTABLES');
$pdf->datosGenerales([
    ['Responsable:', 'María de los Ángeles Alejandra del Rosario Fernández de la Cruz Paredes'],
    ['Documento de Identidad:', '12345678'],
    ['Área:', 'Administración y coordinación de operaciones corporativas'],
], [
    ['Número:', '001'],
    ['Periodo:', '01/10/2026 - 19/10/2026'],
    ['Fecha de Rendición:', '19/10/2026'],
]);
$columnas = [
    ['titulo' => 'N°', 'ancho' => 10, 'align' => 'C'],
    ['titulo' => 'TIPO DE DOCUMENTO', 'ancho' => 30, 'align' => 'C'],
    ['titulo' => 'DESCRIPCIÓN / ASUNTO', 'ancho' => 50],
    ['titulo' => 'FECHA DOC.', 'ancho' => 20, 'align' => 'C'],
    ['titulo' => 'N° PÁG.', 'ancho' => 16, 'align' => 'C'],
    ['titulo' => 'OBSERVACIONES', 'ancho' => 40],
    ['titulo' => 'MONTO', 'ancho' => 20, 'align' => 'R'],
];
$pdf->tabla($columnas, [
    [1, 'Declaración Jurada', 'Compra de útiles y materiales para coordinación de operaciones.', '19/10/2026', 2, 'Texto con tildes: gestión, comisión y traslado.', 'S/ 190.00'],
    [2, 'Invoice', 'Referencia sin espacios: ' . str_repeat('ABCDEFGHIJKLMN', 12), '19/10/2026', 1, 'Conservar íntegra la referencia.', 'S/ 20.00'],
], ['', '', '', '', '', 'TOTAL', 'S/ 210.00']);
$firmas = [];
foreach (['RESPONSABLE', 'RECIBIDO POR CONTABILIDAD', 'APROBADO POR JEFATURA', 'REVISADO POR GERENCIA', 'APROBADO POR CONTABILIDAD'] as $etiqueta) {
    $firmas[] = ['etiqueta' => $etiqueta, 'nombre' => 'María de los Ángeles Fernández de la Cruz Paredes', 'fecha' => '19/10/2026', 'automatico' => true];
}
$pdf->firmas($firmas);

$pdf->nuevaPagina('DOCUMENTOS CONTABLES');
$columnasContables = [
    ['titulo' => 'N°', 'ancho' => 8], ['titulo' => 'FECHA', 'ancho' => 16],
    ['titulo' => 'TIPO DOC.', 'ancho' => 26], ['titulo' => 'SERIE', 'ancho' => 14],
    ['titulo' => 'NÚMERO', 'ancho' => 16], ['titulo' => 'PROVEEDOR', 'ancho' => 46],
    ['titulo' => 'CONCEPTO', 'ancho' => 40], ['titulo' => 'MONTO', 'ancho' => 20, 'align' => 'R'],
];
$filas = [];
for ($i = 1; $i <= 32; $i++) {
    $filas[] = [$i, '19/10/2026', 'Factura electrónica', 'F001', '00012345', 'Comercializadora e Importadora de Equipos y Servicios del Pacífico Sociedad Anónima Cerrada', 'Materiales para mantenimiento de equipos de oficina.', 'S/ 15.00'];
}
$pdf->tabla($columnasContables, $filas, ['', '', '', '', '', '', 'TOTAL', 'S/ 480.00']);
$pdf->firmas($firmas);

$pdf->nuevaPagina('PLANILLA DE MOVILIDAD');
$pdf->tabla([
    ['titulo' => 'N°', 'ancho' => 10], ['titulo' => 'FECHA', 'ancho' => 20],
    ['titulo' => 'ORIGEN', 'ancho' => 40], ['titulo' => 'DESTINO', 'ancho' => 40],
    ['titulo' => 'CONCEPTO DE TRASLADO', 'ancho' => 56], ['titulo' => 'MONTO', 'ancho' => 20],
], [[1, '19/10/2026', 'Oficina principal', 'Sede administrativa', str_repeat('Descripción extensa que debe continuar sin perder texto. ', 150), 'S/ 30.00']], ['', '', '', '', 'TOTAL', 'S/ 30.00']);

$anexo = new FPDF();
$anexo->AddPage('L');
$anexo->SetFont('Arial', '', 12);
$anexo->Cell(0, 10, 'EVIDENCIA ORIGINAL - anexo horizontal de prueba');
$anexo->Output('F', $carpeta . '/evidencia-prueba.pdf');
$paginasAntes = $pdf->PageNo();
$pdf->anexarPdf($carpeta . '/evidencia-prueba.pdf');
if ($pdf->PageNo() !== $paginasAntes + 1) throw new RuntimeException('No se anexó la evidencia.');
$pdf->Output('F', $carpeta . '/resumen-textos-largos.pdf');
echo "OK: {$pdf->celdas} celdas sin desbordamientos, {$pdf->PageNo()} páginas y un anexo horizontal.\n";
