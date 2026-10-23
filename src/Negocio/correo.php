<?php
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../../vendor/PHPMAILER/src/Exception.php';
require_once __DIR__ . '/../../vendor/PHPMAILER/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/PHPMAILER/src/SMTP.php';
require_once __DIR__ . '/../../config/correo.php';

function registrarLogCorreo(array $datos): void
{
    $ruta = __DIR__ . '/../../storage/logs/correo.txt';
    $directorio = dirname($ruta);

    if (!is_dir($directorio) && !@mkdir($directorio, 0775, true) && !is_dir($directorio)) {
        error_log('No se pudo crear el directorio del log de correo.');
        return;
    }

    $campos = [];
    foreach (['resumen_id', 'accion', 'estado', 'usuario_id', 'destinatario', 'detalle'] as $clave) {
        if (!isset($datos[$clave]) || $datos[$clave] === '') continue;
        $valor = preg_replace('/[\r\n|]+/', ' ', (string)$datos[$clave]);
        $campos[] = $clave . '=' . $valor;
    }

    $fecha = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))
        ->format('Y-m-d H:i:s P');
    $linea = $fecha . ' | ' . implode(' | ', $campos) . PHP_EOL;
    if (@file_put_contents($ruta, $linea, FILE_APPEND | LOCK_EX) === false) {
        error_log('No se pudo escribir el log de correo: ' . $ruta);
    }
}

function enviarCorreo(
    string $destinatario,
    string $nombreDestinatario,
    string $asunto,
    string $mensajeHtml,
    string $mensajeTexto,
    ?string $pdf = null,
    ?string $nombrePdf = null,
    array $contexto = []
): array
{
    $config = obtenerConfiguracionCorreo();
    if (!$config['activo']) {
        registrarLogCorreo($contexto + ['estado' => 'OMITIDO', 'detalle' => 'Correo desactivado']);
        return ['ok' => true, 'estado' => 'OMITIDO'];
    }

    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        registrarLogCorreo($contexto + [
            'estado' => 'ERROR',
            'destinatario' => $destinatario,
            'detalle' => 'Destinatario de envío no válido',
        ]);
        return ['ok' => false, 'estado' => 'ERROR', 'error' => 'Destinatario no válido.'];
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->Port = (int)$config['puerto'];
        $mail->SMTPAuth = (bool)$config['smtp_auth'];
        $mail->SMTPAutoTLS = (bool)$config['smtp_auto_tls'];
        $seguridad = strtolower((string)$config['seguridad']);
        if (in_array($seguridad, ['ssl', 'tls'], true)) {
            $mail->SMTPSecure = $seguridad;
        }
        if ($mail->SMTPAuth) {
            $mail->Username = (string)$config['usuario'];
            $mail->Password = (string)$config['password'];
        }
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->Timeout = 20;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($config['remitente'], $config['nombre_remitente']);

        if (!empty($config['reply_to'])) $mail->addReplyTo($config['reply_to']);

        $mail->addAddress($destinatario, $nombreDestinatario);
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $rutaFirma = __DIR__ . '/../../storage/correo/firma_RR._HH_-_2026-10-13.png';
        if (is_file($rutaFirma)) {
            $mail->addEmbeddedImage($rutaFirma, 'firma-correo', 'firma.png');
            $firmaHtml = '<p><img src="cid:firma-correo" alt="Firma de correo" '
                . 'style="display:block;max-width:420px;height:auto"></p>';
        } else {
            $firmaHtml = $config['firma_html'] ?? '';
        }
        $mail->Body = $mensajeHtml . $firmaHtml;
        $mail->AltBody = $mensajeTexto;

        if ($pdf !== null && $nombrePdf !== null) {
            $mail->addStringAttachment(
                $pdf,
                $nombrePdf,
                PHPMailer::ENCODING_BASE64,
                'application/pdf'
            );
        }

        $mail->send();

        registrarLogCorreo($contexto + [
            'estado' => 'ACEPTADO_SMTP',
            'destinatario' => $destinatario,
        ]);

        return [
            'ok' => true,
            'estado' => 'ENVIADO',
            'destinatario' => $destinatario
        ];
    } catch (Throwable $e) {
        error_log('Correo Caja Chica: ' . $e->getMessage());
        registrarLogCorreo($contexto + [
            'estado' => 'ERROR_SMTP',
            'destinatario' => $destinatario,
            'detalle' => $e->getMessage(),
        ]);
        return [
            'ok' => false,
            'estado' => 'ERROR',
            'error' => 'No se pudo enviar el correo.'
        ];
    }
}
