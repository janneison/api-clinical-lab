<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\LabOrderDetail;

interface LabOrderDetailRepositoryInterface
{
    /**
     * @param LabOrderDetail[] $details
     */
    public function saveMany(array $details): void;
}
