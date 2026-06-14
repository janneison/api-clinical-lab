<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\Antibiograma;

interface AntibiogramaRepositoryInterface
{
    /** Guarda el antibiograma y sus items. Retorna el ID generado. */
    public function save(Antibiograma $antibiograma): int;

    /** @return Antibiograma[] con sus items cargados */
    public function findByLabResultId(int $labResultId): array;

    public function deleteByLabResultId(int $labResultId): void;
}
