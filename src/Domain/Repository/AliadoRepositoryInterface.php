<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\Aliado;

interface AliadoRepositoryInterface
{
    public function findById(string $id): ?Aliado;
    public function findAll(): array;
    public function save(Aliado $aliado): void;
}
