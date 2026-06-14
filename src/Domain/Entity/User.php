<?php

namespace ClinicalLab\Domain\Entity;

class User
{
    /** @var string[] */
    private array $aliadoIds = [];

    /** @var int[] */
    private array $healthCenterIds = [];

    public function __construct(
        private readonly int    $id,
        private readonly string $username,
        private readonly string $email,
        private readonly string $passwordHash,
        private readonly Role   $role,
        private readonly bool   $activo,
        private readonly int    $failedLoginAttempts = 0,
        private readonly ?\DateTimeImmutable $lockedUntil = null,
    ) {
    }

    public function getId(): int           { return $this->id; }
    public function getUsername(): string  { return $this->username; }
    public function getEmail(): string     { return $this->email; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function getRole(): Role        { return $this->role; }
    public function isActivo(): bool       { return $this->activo; }
    public function getFailedLoginAttempts(): int { return $this->failedLoginAttempts; }
    public function getLockedUntil(): ?\DateTimeImmutable { return $this->lockedUntil; }

    /** Devuelve true si la cuenta está bloqueada en este momento. */
    public function isLocked(): bool
    {
        return $this->lockedUntil !== null
            && $this->lockedUntil > new \DateTimeImmutable();
    }

    /** @return string[] */
    public function getAliadoIds(): array  { return $this->aliadoIds; }

    public function setAliadoIds(array $ids): void
    {
        $this->aliadoIds = $ids;
    }

    /** @return int[] */
    public function getHealthCenterIds(): array { return $this->healthCenterIds; }

    public function setHealthCenterIds(array $ids): void
    {
        $this->healthCenterIds = array_map('intval', $ids);
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role->getName() === $roleName;
    }

    public function belongsToAliado(string $aliadoId): bool
    {
        return in_array($aliadoId, $this->aliadoIds, true);
    }
}
