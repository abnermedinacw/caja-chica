<?php
require_once __DIR__ . '/firmas.php';
require_once __DIR__ . '/../pdf/resumenPdf.php';
require_once __DIR__ . '/../Datos/documentoContable.php';
require_once __DIR__ . '/../Datos/documentoNoContable.php';
require_once __DIR__ . '/../Datos/planillaMovilidad.php';
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/../Datos/adjuntos.php';
require_once __DIR__ . '/../Vistas/helpers.php';

function rutaAdjuntoInterna(string $rutaRelativa): string
{
    $raiz = realpath(__DIR__ . '/../../storage/uploads');
    if ($raiz === false) {
        throw new RuntimeException('No se encontró el almacenamiento de adjuntos.');
    }

    $ruta = realpath(
        $raiz . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rutaRelativa)
    );

    if (
        $ruta === false
        || !is_file($ruta)
        || strncasecmp($ruta, $raiz . DIRECTORY_SEPARATOR, strlen($raiz) + 1) !== 0
    ) {
        throw new RuntimeException('Uno de los anexos ya no existe en el servidor.');
    }

    return $ruta;
}

function anexarEvidenciasAlResumenPdf(ResumenPdf $pdf, array $anexos): void
{
    foreach ($anexos as $anexo) {
        $ruta = rutaAdjuntoInterna($anexo['ruta']);

        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        if ($extension === 'pdf') {
            $pdf->anexarPdf($ruta);
            continue;
        }

        // Solo para evidencias históricas que el sistema permitía como imagen.
        if (in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            $pdf->anexarImagenAntigua($ruta);
            continue;
        }

        throw new RuntimeException('El anexo ' . $anexo['nombre_archivo'] . ' no puede incorporarse al PDF final.');
    }
}

function generarPdfResumen(int $resumenId, bool $incluirAnexos = true): ?string
{
    $resumen = obtenerResumenAprobador($resumenId);
    if (!$resumen) return null;

    $firmasPdf = prepararFirmasPdf($resumenId, $resumen);

    // Traemos documentos
    // Documento contable
    $documentoContable = obtenerDocumentoContablePorResumen($resumenId);
    $itemsContable = [];
    $totalContable = 0.0;
    if ($documentoContable) {
        $itemsContable = obtenerDetalleDocumentoContable((int)$documentoContable['id']);
        $totalContable = obtenerTotalDocumentoContable((int)$documentoContable['id']);
    }

    // Documento no contable
    $documentoNoContable = obtenerDocumentoNoContablePorResumen($resumenId);
    $itemsNoContable = [];
    $totalNoContable = 0.0;
    if ($documentoNoContable) {
        $itemsNoContable = obtenerDetalleDocumentoNoContable((int)$documentoNoContable['id']);
        $totalNoContable = obtenerTotalDocumentoNoContable((int)$documentoNoContable['id']);
    }

    // Planilla de movilidad
    $planilla = obtenerPlanillaPorResumen($resumenId);
    $itemsPlanilla = [];
    $totalPlanilla = 0.0;
    if ($planilla) {
        $itemsPlanilla = obtenerDetallePlanilla((int)$planilla['id']);
        $totalPlanilla = array_sum(array_column($itemsPlanilla, 'monto'));
    }

    $datosGeneralesIzq = [
        ['Responsable:', $resumen['nombres']],
        ['Documento de Identidad:', $resumen['dni']],
        ['Área:', $resumen['area_nombre']],
    ];

    $datosGeneralesDer = [
        ['Número:', numeroDocumento((int)$resumen['numero'])],
        ['Periodo:', formatearFecha($resumen['periodo_inicio']) . ' - ' . formatearFecha($resumen['periodo_fin'])],
        ['Fecha de Rendición:', formatearFecha($resumen['fecha_emision'])],
    ];

    $pdf = new ResumenPdf();
    // Logo
    $pdf->establecerLogo($resumen['sello_ruta'] ?? null);
    $pdf->establecerRucEmpresa((string)($resumen['empresa_ruc'] ?? ''));
    $pdf->AliasNbPages();

    // Página 1: Resumen
    $pdf->nuevaPagina('RESUMEN FONDO DE CAJA CHICA');
    $pdf->datosGenerales(
        [...$datosGeneralesIzq, ['Fondo asignado:', soles($resumen['monto_asignado'])]],
        [...$datosGeneralesDer, ['Monto a rendir:', soles($resumen['total_egresos'])]]
    );

    $pdf->tituloSeccion('2' . chr(176) . ' RESUMEN DE GASTOS');

    $saldoInicial = (float)$resumen['saldo_inicial'];

    $columnasResumen = [
        ['titulo' => 'N°', 'ancho' => 10, 'align' => 'C'],
        ['titulo' => 'FECHA', 'ancho' => 22, 'align' => 'C'],
        ['titulo' => 'TIPO DE DOCUMENTO', 'ancho' => 58, 'align' => 'C'],
        ['titulo' => 'INGRESOS', 'ancho' => 30, 'align' => 'C'],
        ['titulo' => 'EGRESOS', 'ancho' => 30, 'align' => 'C'],
        ['titulo' => 'SALDOS', 'ancho' => 36, 'align' => 'C'],
    ];

    $filasResumen = [
        ['', '', '', soles($saldoInicial), '', soles($saldoInicial)],
    ];
    $numeroFila = 0;
    $saldoCorrido = $saldoInicial;

    if ($documentoContable) {
        $saldoCorrido -= $totalContable;
        $filasResumen[] = [++$numeroFila, formatearFecha($documentoContable['fecha_rendicion']), 'DOCUMENTOS CONTABLES', '', soles($totalContable), soles($saldoCorrido)];
    }
    if ($documentoNoContable) {
        $saldoCorrido -= $totalNoContable;
        $filasResumen[] = [++$numeroFila, formatearFecha($documentoNoContable['fecha_rendicion']), 'DOCUMENTOS NO CONTABLES', '', soles($totalNoContable), soles($saldoCorrido)];
    }
    if ($planilla) {
        $saldoCorrido -= $totalPlanilla;
        $filasResumen[] = [++$numeroFila, formatearFecha($planilla['fecha_emision']), 'PLANILLA DE MOVILIDAD', '', soles($totalPlanilla), soles($saldoCorrido)];
    }

    $pdf->tabla($columnasResumen, $filasResumen, ['', '', 'TOTALES', soles($saldoInicial), soles($resumen['total_egresos']), soles($saldoCorrido)]);
    $pdf->firmas($firmasPdf);


    // Página 2: Documentos contables
    if ($itemsContable) {
        $pdf->nuevaPagina('DOCUMENTOS CONTABLES');
        $pdf->datosGenerales($datosGeneralesIzq, $datosGeneralesDer);
        $pdf->tituloSeccion('2' . chr(176) . 'DETALLE DE GASTOS');

        $columnas = [
            ['titulo' => 'N°', 'ancho' => 8, 'align' => 'C'],
            ['titulo' => 'FECHA', 'ancho' => 16, 'align' => 'C'],
            ['titulo' => 'TIPO DOC.', 'ancho' => 26, 'align' => 'C'],
            ['titulo' => 'SERIE', 'ancho' => 14, 'align' => 'C'],
            ['titulo' => 'NÚMERO', 'ancho' => 16, 'align' => 'C'],
            ['titulo' => 'PROVEEDOR', 'ancho' => 46, 'align' => 'C'],
            ['titulo' => 'CONCEPTO', 'ancho' => 40, 'align' => 'C'],
            ['titulo' => 'MONTO', 'ancho' => 20, 'align' => 'C'],
        ];

        $filas = array_map(fn($it) => [
            $it['item'],
            $it['fecha'],
            $it['tipo_comprobante'] ?? '-',
            $it['serie'],
            $it['numero_comprobante'],
            $it['proveedor_razon_social'] ?:
                $it['proveedor_ruc'],
            $it['concepto'],
            soles($it['monto']),
        ], $itemsContable);

        $pdf->tabla($columnas, $filas, ['', '', '', '', '', '', 'TOTAL', soles($totalContable)]);
        $pdf->firmas($firmasPdf);
    }

    // Página 3: Documentos no contables
    if ($itemsNoContable) {
        $pdf->nuevaPagina('SUSTENTO DOCUMENTOS NO CONTABLES');
        $pdf->datosGenerales($datosGeneralesIzq, $datosGeneralesDer);
        $pdf->tituloSeccion('2' . chr(176) . ' DETALLE DE GASTOS');

        $columnas = [
            ['titulo' => 'N°', 'ancho' => 10, 'align' => 'C'],
            ['titulo' => 'TIPO DE DOCUMENTO', 'ancho' => 30, 'align' => 'C'],
            ['titulo' => 'DESCRIPCIÓN / ASUNTO', 'ancho' => 50, 'align' => 'C'],
            ['titulo' => 'FECHA DOC.', 'ancho' => 20, 'align' => 'C'],
            ['titulo' => 'N° PÁG.', 'ancho' => 16, 'align' => 'C'],
            ['titulo' => 'OBSERVACIONES', 'ancho' => 40, 'align' => 'C'],
            ['titulo' => 'MONTO', 'ancho' => 20, 'align' => 'C'],
        ];
        $filas = array_map(fn($it) => [
            $it['item'],
            $it['tipo_documento_nombre'],
            $it['descripcion'],
            $it['fecha_documento'],
            $it['numero_paginas'] ?? '-',
            $it['observaciones'] ?: '-',
            soles($it['monto']),
        ], $itemsNoContable);

        $pdf->tabla($columnas, $filas, ['', '', '', '', '', 'TOTAL', soles($totalNoContable)]);
        $pdf->firmas($firmasPdf);
    }

    // Página 4: Planilla de movilidad
    if ($itemsPlanilla) {
        $pdf->nuevaPagina('PLANILLA DE MOVILIDAD');
        $pdf->datosGenerales($datosGeneralesIzq, $datosGeneralesDer);
        $pdf->tituloSeccion('2' . chr(176) . 'DETALLE DE GASTOS');

        $columnas = [
            ['titulo' => 'N°', 'ancho' => 10, 'align' => 'C'],
            ['titulo' => 'FECHA', 'ancho' => 20, 'align' => 'C'],
            ['titulo' => 'ORIGEN', 'ancho' => 40, 'align' => 'C'],
            ['titulo' => 'DESTINO', 'ancho' => 40, 'align' => 'C'],
            ['titulo' => 'CONCEPTO DE TRASLADO', 'ancho' => 56, 'align' => 'C'],
            ['titulo' => 'MONTO', 'ancho' => 20, 'align' => 'C'],
        ];
        $filas = array_map(fn($it) => [
            $it['item'],
            $it['fecha'],
            $it['origen'],
            $it['destino'],
            $it['motivo'],
            soles($it['monto']),
        ], $itemsPlanilla);

        $pdf->tabla($columnas, $filas, ['', '', '', '', 'TOTAL', soles($totalPlanilla)]);
        $pdf->firmas($firmasPdf);
    }

    if ($incluirAnexos) {
        // Los XML quedan disponibles para descarga en la vista, pero nunca se
        // incorporan al expediente visual. Solo se adjuntan evidencias PDF.
        if ($documentoContable) {
            anexarEvidenciasAlResumenPdf(
                $pdf,
                obtenerEvidenciasContablesParaPdf((int)$documentoContable['id'])
            );
        }

        if ($documentoNoContable) {
            anexarEvidenciasAlResumenPdf(
                $pdf,
                obtenerEvidenciasNoContablesParaPdf((int)$documentoNoContable['id'])
            );
        }

        if ($planilla) {
            anexarEvidenciasAlResumenPdf(
                $pdf,
                obtenerEvidenciasMovilidadParaPdf((int)$planilla['id'])
            );
        }
    }

    return $pdf->Output('S'); // 'S' = lo devuelve como string, todavía no lo imprime
}
