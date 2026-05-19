<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\Aliado;

interface AliadoRepositoryInterface
{
    public function findById(string $id): ?Aliado;

    /** @return Aliado[] */
    public function findAll(): array;

    public function save(Aliado $aliado): void;

    public function update(Aliado $aliado): void;

    public function updateLogo(string $id, string $logoPath): void;
}
