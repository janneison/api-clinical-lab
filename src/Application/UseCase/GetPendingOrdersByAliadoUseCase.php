<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Application\Dto\OrderFilterDto;
use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Repository\AliadoRepositoryInterface;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use RuntimeException;

class GetPendingOrdersByAliadoUseCase
{
    public function __construct(
        private readonly LabOrderRepositoryInterface $orderRepository,
        private readonly AliadoRepositoryInterface   $aliadoRepository,
    ) {
    }

    /**
     * Retorna todas las órdenes en estado 'pending' para un aliado dado.
     *
     * @return LabOrder[]
     * @throws RuntimeException si el aliado no existe
     */
    public function execute(string $aliadoId): array
    {
        if (!$this->aliadoRepository->findById($aliadoId)) {
            throw new RuntimeException("Aliado no encontrado: {$aliadoId}");
        }

        return $this->orderRepository->findByFilter(new OrderFilterDto(
            aliadoIds: [$aliadoId],
            estado:    LabOrder::STATUS_PENDING,
            limit:     1000,   // sin paginación — devuelve todas
        ));
    }
}
