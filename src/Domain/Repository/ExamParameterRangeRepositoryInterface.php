<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\ExamParameterRange;

interface ExamParameterRangeRepositoryInterface
{
    /**
     * Rangos activos de un parámetro, opcionalmente filtrados por reactivo/sexo/edad.
     *
     * @return ExamParameterRange[]
     */
    public function findByParameter(
        int     $parameterId,
        ?string $reactivo = null,
        ?string $sexo     = null,
        ?int    $edad     = null
    ): array;

    public function findById(int $id): ?ExamParameterRange;

    public function save(ExamParameterRange $range): int;

    public function update(ExamParameterRange $range): void;

    public function deactivate(int $id): void;
}
