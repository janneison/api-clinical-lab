<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\Medico;

interface MedicoRepositoryInterface
{
    public function findById(int $id): ?Medico;

    public function findByDocument(string $tipoDocumento, string $identificacion): ?Medico;

    public function findByUserId(int $userId): ?Medico;

    /** @return Medico[] */
    public function findAll(?string $search = null, bool $soloActivos = true): array;

    public function save(Medico $medico): int;

    public function update(Medico $medico): void;

    public function deactivate(int $id): void;
}
