<?php

declare(strict_types=1);

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Domain\Repository\UserRepositoryInterface;
use ClinicalLab\Domain\Service\PasswordPolicyService;
use ClinicalLab\Infrastructure\Mail\ResultMailer;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Gestiona el flujo completo de recuperación de contraseña:
 *
 *  1. requestReset($email)  → genera token, lo envía por email.
 *  2. confirmReset($token, $newPassword) → valida token, aplica nueva contraseña.
 */
class PasswordResetUseCase
{
    /** Minutos de validez del token de recuperación. */
    private const TOKEN_TTL_MINUTES = 30;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ResultMailer            $mailer,
        private readonly PasswordPolicyService   $passwordPolicy,
        private readonly string                  $appName = 'Laboratorio Clínico',
    ) {
    }

    /**
     * Paso 1: solicitar recuperación.
     *
     * Siempre responde con éxito para no revelar si el email existe.
     */
    public function requestReset(string $email): void
    {
        $user = $this->userRepository->findByEmail(trim($email));

        if (!$user || !$user->isActivo()) {
            // Respuesta silenciosa — no revelar existencia del email
            return;
        }

        // Generar token criptográficamente seguro
        $rawToken  = bin2hex(random_bytes(32));          // 64 chars hex
        $tokenHash = hash('sha256', $rawToken);          // guardamos el hash, no el token
        $expires   = new DateTimeImmutable('+' . self::TOKEN_TTL_MINUTES . ' minutes');

        $this->userRepository->savePasswordResetToken($user->getId(), $tokenHash, $expires);

        $this->sendResetEmail($user->getEmail(), $user->getUsername(), $rawToken);
    }

    /**
     * Paso 2: confirmar recuperación con el token recibido por email.
     *
     * @throws RuntimeException si el token es inválido/expirado o la contraseña no cumple la política.
     */
    public function confirmReset(string $rawToken, string $newPassword): void
    {
        $tokenHash = hash('sha256', trim($rawToken));
        $user      = $this->userRepository->findByResetToken($tokenHash);

        if (!$user) {
            throw new RuntimeException('El enlace de recuperación es inválido o ha expirado.');
        }

        // Validar política de contraseñas
        try {
            $this->passwordPolicy->validate($newPassword);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage());
        }

        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->userRepository->updatePassword($user->getId(), $passwordHash);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function sendResetEmail(string $toEmail, string $username, string $rawToken): void
    {
        $subject = "[{$this->appName}] Recuperación de contraseña";

        $htmlBody = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px;border:1px solid #e0e0e0;border-radius:8px'>
            <h2 style='color:#1a5276;margin-top:0'>{$this->appName}</h2>
            <p>Hola <strong>" . htmlspecialchars($username) . "</strong>,</p>
            <p>Recibimos una solicitud para restablecer tu contraseña.</p>
            <p>Usa el siguiente token en el endpoint <code>POST /auth/password-reset/confirm</code>:</p>
            <div style='font-size:14px;font-weight:bold;letter-spacing:2px;text-align:center;padding:18px;background:#f0f4f8;border-radius:8px;margin:20px 0;color:#1a5276;word-break:break-all'>{$rawToken}</div>
            <p>Este token es válido por <strong>" . self::TOKEN_TTL_MINUTES . " minutos</strong>.</p>
            <p>Si no solicitaste este cambio, ignora este mensaje. Tu contraseña no será modificada.</p>
            <p style='color:#666;font-size:12px;border-top:1px solid #eee;padding-top:12px;margin-top:20px'>{$this->appName} — sistema de laboratorio clínico</p>
            </div>
        ";

        $altBody = "Hola {$username},\n\nToken de recuperación: {$rawToken}\n"
                 . "Válido por " . self::TOKEN_TTL_MINUTES . " minutos.\n\n"
                 . "Envíalo a POST /auth/password-reset/confirm con tu nueva contraseña.\n\n"
                 . "Si no solicitaste este cambio, ignora este mensaje.";

        $this->mailer->sendHtml($toEmail, $subject, $htmlBody, $altBody);
    }
}
