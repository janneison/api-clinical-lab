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
}
