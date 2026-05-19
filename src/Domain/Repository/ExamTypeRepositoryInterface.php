<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\ExamType;

interface ExamTypeRepositoryInterface
{
    /** @return ExamType[] */
    public function findAll(bool $soloActivos = true): array;

    public function findByCups(string $cups): ?ExamType;

    public function save(ExamType $examType): void;

    public function update(ExamType $examType): void;
}
