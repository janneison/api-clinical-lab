<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\User;

interface UserRepositoryInterface
{
    public function findByUsername(string $username): ?User;
    public function findByEmail(string $email): ?User;
    public function findById(int $id): ?User;
    public function save(User $user): int; // returns new id
    public function assignAliado(int $userId, string $aliadoId): void;
    public function removeAliado(int $userId, string $aliadoId): void;

    /** Asocia un usuario a un centro de salud. */
    public function assignHealthCenter(int $userId, int $healthCenterId): void;

    /** Desasocia un usuario de un centro de salud. */
    public function removeHealthCenter(int $userId, int $healthCenterId): void;

    /** Reemplaza todos los centros de salud del usuario por los dados. */
    public function syncHealthCenters(int $userId, array $healthCenterIds): void;

    // ── Seguridad: bloqueo por intentos fallidos ──────────────────────────────

    /** Incrementa el contador de intentos fallidos. Si llega a 4, bloquea la cuenta. */
    public function incrementFailedAttempts(int $userId): void;

    /** Resetea el contador de intentos fallidos y elimina el bloqueo. */
    public function resetFailedAttempts(int $userId): void;

    // ── Seguridad: recuperación de contraseña ─────────────────────────────────

    /** Guarda el hash del token de recuperación y su expiración. */
    public function savePasswordResetToken(int $userId, string $tokenHash, \DateTimeImmutable $expires): void;

    /** Busca un usuario por el hash del token de recuperación (solo tokens no expirados). */
    public function findByResetToken(string $tokenHash): ?User;

    /** Actualiza el hash de la contraseña y limpia el token de recuperación. */
    public function updatePassword(int $userId, string $passwordHash): void;
}
