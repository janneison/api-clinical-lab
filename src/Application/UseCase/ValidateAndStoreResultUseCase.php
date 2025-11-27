<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Application\Dto\LabResultDto;
use ClinicalLab\Domain\Entity\LabResult;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use ClinicalLab\Domain\Repository\LabResultRepositoryInterface;
use DateTimeImmutable;
use RuntimeException;

class ValidateAndStoreResultUseCase
{
    public function __construct(
        private readonly LabOrderRepositoryInterface $orderRepository,
        private readonly LabResultRepositoryInterface $resultRepository
    ) {
    }

    public function execute(LabResultDto $dto): void
    {
        $order = $this->orderRepository->findByIdSolicitudKey($dto->idSolicitudKey);
        if (!$order) {
            throw new RuntimeException('Orden no encontrada');
        }

        $this->assertRequiredFields($dto);

        $result = new LabResult(
            $dto->idSolicitudKey,
            $dto->cups,
            $dto->values,
            $dto->attachmentPath,
            new DateTimeImmutable()
        );

        $this->resultRepository->save($result);
        $order->updateProgress(100);
        $this->orderRepository->update($order);
    }

    private function assertRequiredFields(LabResultDto $dto): void
    {
        if (!isset($dto->values['resultado'])) {
            throw new RuntimeException('El resultado debe incluir un valor de resultado principal.');
        }
    }
}
