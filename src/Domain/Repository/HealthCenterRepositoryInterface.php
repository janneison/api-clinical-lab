<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\HealthCenter;

interface HealthCenterRepositoryInterface
{
    /** @return HealthCenter[] */
    public function findAll(bool $soloActivos = true): array;

    public function findById(int $id): ?HealthCenter;

    public function findByNombre(string $nombre): ?HealthCenter;

    public function save(HealthCenter $center): int;

    public function update(HealthCenter $center): void;

    /** @return HealthCenter[] */
    public function findByAliadoId(string $aliadoId): array;

    public function associateAliado(int $healthCenterId, string $aliadoId): void;

    public function dissociateAliado(int $healthCenterId, string $aliadoId): void;
}
