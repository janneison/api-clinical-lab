<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Application\Dto\OrderFilterDto;
use ClinicalLab\Domain\Entity\LabOrder;

interface LabOrderRepositoryInterface
{
    public function save(LabOrder $order): void;

    public function findByIdSolicitudKey(string $idSolicitudKey): ?LabOrder;

    public function update(LabOrder $order): void;

    /**
     * @return LabOrder[]
     */
    public function findByFilter(OrderFilterDto $filter): array;

    public function countByFilter(OrderFilterDto $filter): int;

    public function findByIdentificacion(string $identificacion, ?string $estado = null): array;
}
