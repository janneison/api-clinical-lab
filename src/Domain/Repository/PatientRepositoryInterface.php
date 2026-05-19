<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\Patient;

interface PatientRepositoryInterface
{
    public function findById(int $id): ?Patient;

    public function findByDocument(string $tipoDocumento, string $identificacion): ?Patient;

    /** @return Patient[] */
    public function findAll(?string $search = null, int $page = 1, int $limit = 20): array;

    public function countAll(?string $search = null): int;

    public function save(Patient $patient): int;

    public function update(Patient $patient): void;
}
