<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\LabResult;

interface LabResultRepositoryInterface
{
    public function save(LabResult $result): void;
}
