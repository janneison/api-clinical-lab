<?php

namespace ClinicalLab\Infrastructure\Auth;

use ClinicalLab\Domain\Service\TokenServiceInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Throwable;

class JwtTokenService implements TokenServiceInterface
{
    private const ALGORITHM = 'HS256';
    private const TTL       = 3600; // 1 hora

    public function __construct(private readonly string $secret)
    {
    }

    public function generate(array $claims): string
    {
        $now = time();

        $payload = array_merge($claims, [
            'iss' => 'api-clinical-lab',
            'iat' => $now,
            'exp' => $now + self::TTL,
        ]);

        return JWT::encode($payload, $this->secret, self::ALGORITHM);
    }

    public function validate(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
            return (array) $decoded;
        } catch (Throwable $e) {
            throw new RuntimeException('Token inválido o expirado: ' . $e->getMessage());
        }
    }
}
