<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Domain\Repository\PatientRepositoryInterface;
use ClinicalLab\Infrastructure\Mail\ResultMailer;
use ClinicalLab\Infrastructure\Persistence\MySqlPatientAccessTokenRepository;
use DateTimeImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

class PatientPortalUseCase
{
    private const OTP_TTL_MINUTES  = 15;
    private const JWT_TTL_SECONDS  = 3600; // 1 hora
    private const JWT_ISSUER       = 'api-clinical-lab-patient';

    public function __construct(
        private readonly PatientRepositoryInterface          $patientRepository,
        private readonly MySqlPatientAccessTokenRepository   $tokenRepository,
        private readonly ResultMailer                        $mailer,
        private readonly string                              $jwtSecret,
    ) {
    }

    // ── Paso 1: Solicitar acceso ──────────────────────────────────────────────

    public function requestAccess(string $tipoDocumento, string $identificacion): void
    {
        $patient = $this->patientRepository->findByDocument($tipoDocumento, $identificacion);

        if (!$patient) {
            // Respuesta genérica para no revelar si el paciente existe
            throw new RuntimeException('Si el documento está registrado, recibirás un código en tu correo.');
        }

        if (!$patient->getEmail()) {
            throw new RuntimeException('No hay un correo registrado para este documento. Contacta al laboratorio.');
        }

        // Generar OTP de 6 dígitos
        $otp      = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash     = password_hash($otp, PASSWORD_BCRYPT);
        $expiresAt = new DateTimeImmutable('+' . self::OTP_TTL_MINUTES . ' minutes');

        $this->tokenRepository->save($patient->getId(), $hash, $expiresAt);

        // Enviar correo
        $mensaje = "Tu código de acceso al portal de resultados es:\n\n"
                 . "    {$otp}\n\n"
                 . "Este código es válido por " . self::OTP_TTL_MINUTES . " minutos.\n"
                 . "Si no solicitaste este acceso, ignora este mensaje.";

        $this->mailer->sendOtp(
            $patient->getEmail(),
            $patient->getNombre(),
            $otp,
            self::OTP_TTL_MINUTES
        );
    }

    // ── Paso 2: Verificar código y emitir JWT ─────────────────────────────────

    public function verify(string $tipoDocumento, string $identificacion, string $codigo): array
    {
        $patient = $this->patientRepository->findByDocument($tipoDocumento, $identificacion);

        if (!$patient) {
            throw new RuntimeException('Código inválido o expirado.');
        }

        $token = $this->tokenRepository->findValid($patient->getId());

        if (!$token || !password_verify($codigo, $token['codigo_hash'])) {
            throw new RuntimeException('Código inválido o expirado.');
        }

        $this->tokenRepository->markUsed((int) $token['id']);

        // Emitir JWT de paciente
        $now     = time();
        $payload = [
            'iss'            => self::JWT_ISSUER,
            'sub'            => $patient->getId(),
            'nombre'         => $patient->getNombre(),
            'tipoDocumento'  => $patient->getTipoDocumento(),
            'identificacion' => $patient->getIdentificacion(),
            'iat'            => $now,
            'exp'            => $now + self::JWT_TTL_SECONDS,
        ];

        $jwt = JWT::encode($payload, $this->jwtSecret, 'HS256');

        return [
            'token'  => $jwt,
            'patient' => [
                'id'             => $patient->getId(),
                'nombre'         => $patient->getNombre(),
                'tipoDocumento'  => $patient->getTipoDocumento(),
                'identificacion' => $patient->getIdentificacion(),
            ],
            'expiresIn' => self::JWT_TTL_SECONDS,
        ];
    }

    // ── Validar JWT de paciente ───────────────────────────────────────────────

    public function validateToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            $claims  = (array) $decoded;

            if (($claims['iss'] ?? '') !== self::JWT_ISSUER) {
                throw new RuntimeException('Token inválido');
            }

            return $claims;
        } catch (\Throwable $e) {
            throw new RuntimeException('Token de paciente inválido o expirado: ' . $e->getMessage());
        }
    }
}
