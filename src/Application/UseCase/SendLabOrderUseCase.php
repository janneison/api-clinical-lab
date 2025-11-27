<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use ClinicalLab\Domain\Service\ExternalLabClientInterface;
use DateTimeImmutable;
use RuntimeException;

class SendLabOrderUseCase
{
    public function __construct(
        private readonly LabOrderRepositoryInterface $orderRepository,
        private readonly ExternalLabClientInterface $externalLabClient
    ) {
    }

    public function execute(string $idSolicitudKey): void
    {
        $order = $this->orderRepository->findByIdSolicitudKey($idSolicitudKey);
        if (!$order) {
            throw new RuntimeException('Orden no encontrada');
        }

        $this->externalLabClient->sendOrder($order, $order->getDetails());
        $order->markAsSent(new DateTimeImmutable());
        $this->orderRepository->update($order);
    }
}
