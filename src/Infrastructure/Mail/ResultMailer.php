<?php

namespace ClinicalLab\Infrastructure\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
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
     *
     * @throws RuntimeException si el envío falla
     */
    public function sendOtp(
        string $toEmail,
        string $toName,
        string $otp,
        int    $ttlMinutes
    ): void {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $this->host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->username;
        $mail->Password   = $this->password;
        $mail->SMTPSecure = $this->port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $this->port;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($this->username, $this->fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo($this->username, $this->fromName);

        $mail->isHTML(true);
        $mail->Subject = 'Código de acceso al portal de resultados';

        $htmlBody = '
            <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto">
              <h2 style="color:#2c3e50">Portal de Resultados</h2>
              <p>Estimado/a <strong>' . htmlspecialchars($toName) . '</strong>,</p>
              <p>Tu código de acceso es:</p>
              <div style="font-size:36px;font-weight:bold;letter-spacing:8px;text-align:center;
                          padding:16px;background:#f4f4f4;border-radius:8px;margin:16px 0">'
                . htmlspecialchars($otp) .
              '</div>
              <p>Este código es válido por <strong>' . $ttlMinutes . ' minutos</strong>.</p>
              <p style="color:#888;font-size:12px">Si no solicitaste este acceso, ignora este mensaje.</p>
            </div>';

        $mail->Body    = $htmlBody;
        $mail->AltBody = "Tu código de acceso es: {$otp}\nVálido por {$ttlMinutes} minutos.";

        if (!$mail->send()) {
            throw new RuntimeException('Error al enviar el OTP: ' . $mail->ErrorInfo);
        }
    }

    /**
     * Envía el PDF de resultados por correo.
     *
     * @throws RuntimeException si el envío falla
     */
    public function send(
        string  $toEmail,
        string  $toName,
        string  $idSolicitudKey,
        string  $pdfFullPath,
        ?string $mensajePersonalizado = null
    ): void {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $this->host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->username;
        $mail->Password   = $this->password;
        $mail->SMTPSecure = $this->port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $this->port;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($this->username, $this->fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo($this->username, $this->fromName);

        $mail->isHTML(true);
        $mail->Subject = "Resultados de laboratorio - Orden {$idSolicitudKey}";

        $mensaje = $mensajePersonalizado
            ?? "Estimado/a {$toName},\n\nAdjunto encontrará los resultados de su examen de laboratorio correspondiente a la orden {$idSolicitudKey}.\n\nSaludos,\n{$this->fromName}";

        $mail->Body    = nl2br(htmlspecialchars($mensaje))
            . '<br><br><small style="color:#888">Este correo fue generado automáticamente. Por favor no responda a este mensaje.</small>';
        $mail->AltBody = $mensaje;

        if (file_exists($pdfFullPath)) {
            $mail->addAttachment($pdfFullPath, "resultado_{$idSolicitudKey}.pdf");
        } else {
            throw new RuntimeException("El archivo PDF no existe: {$pdfFullPath}");
        }

        if (!$mail->send()) {
            throw new RuntimeException('Error al enviar el correo: ' . $mail->ErrorInfo);
        }
    }
}
