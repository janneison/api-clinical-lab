<?php

namespace ClinicalLab\Domain\Entity;

class User
{
    /** @var string[] */
    private array $aliadoIds = [];

    public function __construct(
        private readonly int    $id,
        private readonly string $username,
        private readonly string $email,
        private readonly string $passwordHash,
        private readonly Role   $role,
        private readonly bool   $activo
    ) {
    }

    public function getId(): int           { return $this->id; }
    public function getUsername(): string  { return $this->username; }
    public function getEmail(): string     { return $this->email; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function getRole(): Role        { return $this->role; }
    public function isActivo(): bool       { return $this->activo; }

    /** @return string[] */
    public function getAliadoIds(): array  { return $this->aliadoIds; }

    public function setAliadoIds(array $ids): void
    {
        $this->aliadoIds = $ids;
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
