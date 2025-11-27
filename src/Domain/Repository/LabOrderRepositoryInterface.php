<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\LabOrder;

interface LabOrderRepositoryInterface
{
    public function save(LabOrder $order): void;

    public function findByIdSolicitudKey(string $idSolicitudKey): ?LabOrder;

    public function update(LabOrder $order): void;
}
