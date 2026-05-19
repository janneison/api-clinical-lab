<?php

namespace ClinicalLab\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;

class MySqlPatientAccessTokenRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(int $patientId, string $codigoHash, DateTimeImmutable $expiresAt): void
    {
        // Invalidar tokens anteriores no usados del mismo paciente
        $this->connection->prepare(
            'UPDATE patient_access_tokens SET usado = 1
             WHERE patient_id = :pid AND usado = 0'
        )->execute(['pid' => $patientId]);

        $this->connection->prepare(
            'INSERT INTO patient_access_tokens (patient_id, codigo_hash, expires_at, usado)
             VALUES (:patient_id, :codigo_hash, :expires_at, 0)'
        )->execute([
            'patient_id'  => $patientId,
            'codigo_hash' => $codigoHash,
            'expires_at'  => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Busca un token válido (no usado, no expirado) para el paciente.
     * Retorna el row o null.
     */
    public function findValid(int $patientId): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM patient_access_tokens
             WHERE patient_id = :pid
               AND usado = 0
               AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['pid' => $patientId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markUsed(int $tokenId): void
    {
        $this->connection->prepare(
            'UPDATE patient_access_tokens SET usado = 1 WHERE id = :id'
        )->execute(['id' => $tokenId]);
    }
}
