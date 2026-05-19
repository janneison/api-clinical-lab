<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\ExamParameter;

interface ExamParameterRepositoryInterface
{
    /**
     * Parámetros activos de un examen, opcionalmente filtrados por sexo y edad.
     *
     * @return ExamParameter[]
     */
    public function findByCups(string $cups, ?string $sexo = null, ?int $edad = null): array;

    public function findById(int $id): ?ExamParameter;

    public function save(ExamParameter $parameter): int;

    public function update(ExamParameter $parameter): void;

    public function deactivate(int $id): void;
}
