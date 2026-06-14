<?php

namespace ClinicalLab\Infrastructure\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

class ResultMailer
{
    public function __construct(
        private readonly string $host,
        private readonly int    $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $fromName,
    ) {
    }

    /**
     * Envía el código OTP al paciente para acceso al portal.
     */
    public function sendOtp(
        string $toEmail,
        string $toName,
        string $otp,
        int    $ttlMinutes
    ): void {
        $mail = $this->buildMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = '[Portal de Resultados] Tu código de acceso';

        $mail->Body = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head><body>'
            . '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px;border:1px solid #e0e0e0;border-radius:8px">'
            . '<h2 style="color:#1a5276;margin-top:0">Portal de Resultados de Laboratorio</h2>'
            . '<p>Estimado/a <strong>' . htmlspecialchars($toName) . '</strong>,</p>'
            . '<p>Tu código de acceso es:</p>'
            . '<div style="font-size:38px;font-weight:bold;letter-spacing:10px;text-align:center;'
            . 'padding:18px;background:#f0f4f8;border-radius:8px;margin:20px 0;color:#1a5276">'
            . htmlspecialchars($otp)
            . '</div>'
            . '<p>Este código es válido por <strong>' . $ttlMinutes . ' minutos</strong>.</p>'
            . '<p style="color:#666;font-size:12px;border-top:1px solid #eee;padding-top:12px;margin-top:20px">'
            . 'Si no solicitaste este acceso, ignora este mensaje. Nadie más puede usar este código.'
            . '</p>'
            . '</div></body></html>';

        $mail->AltBody = "Hola {$toName},\n\nTu código de acceso al portal de resultados es: {$otp}\n"
            . "Válido por {$ttlMinutes} minutos.\n\n"
            . "Si no solicitaste este acceso, ignora este mensaje.";

        if (!$mail->send()) {
            throw new RuntimeException('Error al enviar el OTP: ' . $mail->ErrorInfo);
        }
    }

    /**
     * Envía un email HTML genérico (sin adjunto).
     * Usado para recuperación de contraseña y notificaciones del sistema.
     */
    public function sendHtml(
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $altBody = '',
    ): void {
        $mail = $this->buildMailer();
        $mail->addAddress($toEmail);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody ?: strip_tags($htmlBody);

        if (!$mail->send()) {
            throw new RuntimeException('Error al enviar el correo: ' . $mail->ErrorInfo);
        }
    }

    /**
     * Envía el PDF de resultados por correo.
     */
    public function send(
        string  $toEmail,
        string  $toName,
        string  $idSolicitudKey,
        string  $pdfFullPath,
        ?string $mensajePersonalizado = null
    ): void {
        if (!file_exists($pdfFullPath)) {
            throw new RuntimeException("El archivo PDF no existe: {$pdfFullPath}");
        }

        $mail = $this->buildMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = "[{$this->fromName}] Resultados de laboratorio - Orden {$idSolicitudKey}";

        $cuerpo = $mensajePersonalizado
            ?? "Estimado/a {$toName},\n\nAdjunto encontrará los resultados de su examen de laboratorio correspondiente a la orden {$idSolicitudKey}.\n\nSaludos,\n{$this->fromName}";

        $mail->Body = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head><body>'
            . '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px;border:1px solid #e0e0e0;border-radius:8px">'
            . '<h2 style="color:#1a5276;margin-top:0">' . htmlspecialchars($this->fromName) . '</h2>'
            . '<p>' . nl2br(htmlspecialchars($cuerpo)) . '</p>'
            . '<p style="color:#666;font-size:12px;border-top:1px solid #eee;padding-top:12px;margin-top:20px">'
            . 'Este correo fue generado automáticamente. Por favor no responda a este mensaje.'
            . '</p>'
            . '</div></body></html>';

        $mail->AltBody = $cuerpo . "\n\n---\nEste correo fue generado automáticamente.";

        $mail->addAttachment($pdfFullPath, "resultado_{$idSolicitudKey}.pdf");

        if (!$mail->send()) {
            throw new RuntimeException('Error al enviar el correo: ' . $mail->ErrorInfo);
        }
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function buildMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $this->host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->username;
        $mail->Password   = $this->password;
        $mail->SMTPSecure = $this->port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $this->port;
        $mail->CharSet    = 'UTF-8';
        $mail->XMailer    = ' ';  // oculta "PHPMailer" del header X-Mailer

        $mail->setFrom($this->username, $this->fromName);
        $mail->addReplyTo($this->username, $this->fromName);
        $mail->isHTML(true);

        // Cabeceras que mejoran la entregabilidad
        $mail->addCustomHeader('X-Priority', '3');
        $mail->addCustomHeader('X-Mailer', $this->fromName);
        $mail->addCustomHeader('Precedence', 'bulk');

        return $mail;
    }
}
