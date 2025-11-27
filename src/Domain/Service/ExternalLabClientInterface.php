<?php

namespace ClinicalLab\Domain\Service;

use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Entity\LabOrderDetail;

interface ExternalLabClientInterface
{
    /**
     * @param LabOrderDetail[] $details
     */
    public function sendOrder(LabOrder $order, array $details): void;
}
