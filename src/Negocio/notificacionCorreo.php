<?php
require_once __DIR__ . '/../Datos/cajaChica.php';
require_once __DIR__ . '/../Datos/usuarios.php';
require_once __DIR__ . '/../Datos/cargos.php';
require_once __DIR__ . '/../Datos/nivelAprobacion.php';
require_once __DIR__ . '/exportarPdf.php';
require_once __DIR__ . '/correo.php';
require_once __DIR__ . '/../../config/correo.php';
require_once __DIR__ . '/../Datos/trabajadores.php';

function notificar(int $resumenId, string $accion, ?int $exceptoUsuarioId = null): void
{
    $contexto = ['resumen_id' => $resumenId, 'accion' => $accion];

    try {
        $config = obtenerConfiguracionCorreo();
        if (empty($config['activo'])) {
            registrarLogCorreo($contexto + ['estado' => 'OMITIDO', 'detalle' => 'Correo desactivado']);
            return;
        }

        $resumen = obtenerResumenAprobador($resumenId);
        if (!$resumen) {
            registrarLogCorreo($contexto + ['estado' => 'OMITIDO', 'detalle' => 'Resumen no encontrado']);
            return;
        }

        $hayNivelPendiente = $resumen['nivel_actual_id'] !== null;
        $destinatariosIds = [];

        if ($hayNivelPendiente) {
            if ($resumen['nivel_actual_rol'] === 'JEFATURA') {
                $jefe = buscarJefeTrabajador((int)$resumen['trabajador_id']);
                if ($jefe && !empty($jefe['usuario_id'])) {
                    $destinatariosIds[] = (int)$jefe['usuario_id'];
                }
            } else {
                $encargados = obtenerEncargadosNivel((int)$resumen['nivel_actual_id']);
                $destinatariosIds = array_map('intval', array_column($encargados, 'id'));
            }
        } else {
            if (!empty($resumen['solicitante_usuario_id'])) {
                $destinatariosIds[] = (int)$resumen['solicitante_usuario_id'];
            }
        }

        if ($exceptoUsuarioId !== null) {
            $destinatariosIds = array_values(array_filter(
                $destinatariosIds,
                fn(int $id): bool => $id !== $exceptoUsuarioId
            ));
        }

        if ($hayNivelPendiente && !empty($resumen['solicitante_usuario_id'])) {
            $solicitanteId = (int)$resumen['solicitante_usuario_id'];
            $destinatariosIds = array_values(array_filter(
                $destinatariosIds,
                fn(int $id): bool => $id !== $solicitanteId
            ));
        }

        $destinatariosIds = array_values(array_unique($destinatariosIds));

        if (empty($destinatariosIds)) {
            registrarLogCorreo($contexto + ['estado' => 'OMITIDO', 'detalle' => 'No hay destinatarios asignados']);
            return;
        }

        $pdf = null;
        try {
            $pdf = generarPdfResumen($resumenId, false);
        } catch (Throwable $e) {
            error_log("PDF de correo, Caja Chica {$resumenId}: " . $e->getMessage());
            registrarLogCorreo($contexto + ['estado' => 'PDF_OMITIDO', 'detalle' => $e->getMessage()]);
        }

        $numero = numeroDocumento((int)$resumen['numero']);
        $empresa = (string)$resumen['empresa'];
        $solicitante = (string)$resumen['nombres'];
        $etapa = trim((string)($resumen['nivel_actual_nombre'] ?? ''));
        $enEtapa = $etapa !== '' ? " en {$etapa}" : '';
        $ruta = $hayNivelPendiente ? 'ver-aprobacion' : 'ver-resumen';
        $baseUrl = rtrim((string)($config['app_url'] ?? ''), '/');
        $seguro = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        foreach ($destinatariosIds as $destinatarioId) {
            $contextoDest = $contexto + ['usuario_id' => $destinatarioId];
            $destinatario = obtenerUsuarioNotificacion($destinatarioId);
            if (!$destinatario) {
                registrarLogCorreo($contextoDest + ['estado' => 'OMITIDO', 'detalle' => 'Usuario inexistente o inactivo']);
                continue;
            }

            $correoOriginal = trim((string)($destinatario['correo'] ?? ''));
            $correoPrueba = trim((string)($config['destinatario_prueba'] ?? ''));

            if ($correoPrueba !== '' && !filter_var($correoPrueba, FILTER_VALIDATE_EMAIL)) {
                registrarLogCorreo($contextoDest + ['estado' => 'ERROR', 'detalle' => 'Destinatario de prueba no válido']);
                continue;
            }
            if ($correoPrueba === '' && !filter_var($correoOriginal, FILTER_VALIDATE_EMAIL)) {
                registrarLogCorreo($contextoDest + ['estado' => 'OMITIDO', 'detalle' => 'Correo ausente o no válido']);
                continue;
            }

            $correoEnvio = $correoPrueba !== '' ? $correoPrueba : $correoOriginal;
            $nombreEnvio = $correoPrueba !== '' ? 'Buzón de pruebas' : (string)$destinatario['nombres'];
            if ($correoPrueba !== '') {
                $contextoDest['detalle'] = 'Entrega redirigida a la casilla de prueba';
            }

            $numeroRevision = 0;
            $mensajeRevision = '';
            if ($hayNivelPendiente) {
                $numeroRevision = contarRevisionesUsuarioNivel(
                    $resumenId,
                    $destinatarioId,
                    (int)$resumen['nivel_actual_id']
                ) + 1;
                $ordinal = [1 => 'primera', 2 => 'segunda', 3 => 'tercera'][$numeroRevision]
                    ?? "{$numeroRevision}.ª";
                $mensajeRevision = "Esta es la {$ordinal} vez que te corresponde revisar esta Caja Chica{$enEtapa}.";
            }

            if ($resumen['estado_general'] === 'VISADO') {
                $titulo = 'Caja Chica aprobada';
                $situacion = 'La revisión de tu Caja Chica ha finalizado.';
            } elseif ($resumen['estado_general'] === 'RECHAZADO' && !$hayNivelPendiente) {
                $titulo = 'Caja Chica rechazada';
                $situacion = 'Tu Caja Chica fue observada. Abre el resumen para revisar el motivo y corregirlo.';
            } elseif ($resumen['estado_general'] === 'RECHAZADO') {
                $titulo = 'Observación pendiente';
                $situacion = "Hay una observación que requiere tu revisión{$enEtapa}.";
            } elseif ($accion === 'ENVIADO' && $numeroRevision > 1) {
                $titulo = 'Rendición corregida';
                $situacion = "El solicitante corrigió y necesita tu revisión{$enEtapa}.";
            } elseif ($accion === 'ENVIADO') {
                $titulo = 'Caja Chica pendiente de revisión';
                $situacion = "Se envió una rendición que requiere tu revisión{$enEtapa}.";
            } else {
                $titulo = 'Caja Chica pendiente de revisión';
                $situacion = "La rendición avanzó y ahora requiere tu revisión{$enEtapa}.";
            }

            $asunto = "Caja Chica: Resumen N°{$numero} - {$empresa}";
            $texto = "{$titulo}\n\n"
                . "{$situacion}\n\n"
                . ($mensajeRevision !== '' ? "{$mensajeRevision}\n\n" : '')
                . "Resumen: N.º {$numero}\n"
                . "Empresa: {$empresa}\n"
                . "Solicitante: {$solicitante}\n";

            if ($correoPrueba !== '') {
                $correoPrevisto = $correoOriginal !== '' ? $correoOriginal : 'sin correo registrado';
                $texto .= "\nPrueba: destinatario previsto: {$destinatario['nombres']}"
                    . " ({$correoPrevisto}).\n";
            }

            $html = '<div style="max-width:600px;font-family:Arial,sans-serif;'
                . 'color:#14283f;line-height:1.55">'
                . '<h2 style="margin:0 0 12px;font-size:20px;color:#14283f">'
                . $seguro($titulo)
                . '</h2>'
                . '<p style="margin:0 0 18px">' . $seguro($situacion) . '</p>'
                . ($mensajeRevision !== ''
                    ? '<p style="margin:0 0 18px">' . $seguro($mensajeRevision) . '</p>'
                    : '')
                . '<p style="margin:0 0 18px;color:#4c5a6a">'
                . '<strong>Resumen:</strong> N.º ' . $seguro($numero)
                . '<br><strong>Empresa:</strong> ' . $seguro($empresa)
                . '<br><strong>Solicitante:</strong> ' . $seguro($solicitante)
                . '</p>';

            if ($correoPrueba !== '') {
                $html .= '<p style="color:#4c5a6a;font-size:13px">Prueba: destinatario previsto: '
                    . $seguro((string)$destinatario['nombres'])
                    . ' (' . $seguro($correoPrevisto) . ').</p>';
            }

            if ($baseUrl !== '') {
                $url = "{$baseUrl}/{$ruta}?id={$resumenId}";
                $texto .= "\nAbrir resumen: {$url}\n";

                $html .= '<table role="presentation" border="0" cellpadding="0" '
                    . 'cellspacing="0" style="margin:20px 0">'
                    . '<tr><td bgcolor="#f2a16e" '
                    . 'style="padding:12px 20px;border-radius:6px">'
                    . '<a href="' . $seguro($url) . '" '
                    . 'style="color:#14283f;text-decoration:none;font-size:14px;'
                    . 'font-weight:bold">Abrir resumen</a>'
                    . '</td></tr></table>';
            }

            $html .= '</div>';

            enviarCorreo(
                $correoEnvio,
                $nombreEnvio,
                $asunto,
                $html,
                $texto,
                $pdf,
                $pdf !== null ? "resumen-{$resumenId}.pdf" : null,
                $contextoDest
            );
        }
    } catch (Throwable $e) {
        error_log("Notificación del resumen {$resumenId}: " . $e->getMessage());
        registrarLogCorreo($contexto + ['estado' => 'ERROR', 'detalle' => $e->getMessage()]);
    }
}
