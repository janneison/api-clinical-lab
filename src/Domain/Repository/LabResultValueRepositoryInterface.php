<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\LabResultValue;

interface LabResultValueRepositoryInterface
{
    public function save(LabResultValue $value): void;

    /** @return LabResultValue[] */
    public function findByLabResultId(int $labResultId): array;
}
