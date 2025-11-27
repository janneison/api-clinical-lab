<?php

declare(strict_types=1);

namespace Tests\Unit;

use ClinicalLab\Application\Dto\LabOrderDetailDto;
use ClinicalLab\Application\Dto\LabOrderRequestDto;
use ClinicalLab\Application\UseCase\CreateLabOrderUseCase;
use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Repository\LabOrderDetailRepositoryInterface;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use PHPUnit\Framework\TestCase;

class CreateLabOrderUseCaseTest extends TestCase
{
    public function testCreatesOrderAndPersistsDetails(): void
    {
        $orderRepository = $this->createMock(LabOrderRepositoryInterface::class);
        $detailRepository = $this->createMock(LabOrderDetailRepositoryInterface::class);

        $dto = new LabOrderRequestDto(
            'REQ-123',
            'ADM-1',
            'ATT-1',
            'CC',
            '100200300',
            'Paciente Demo',
            'M',
            '1980-01-02',
            'Hospital Central',
            '2024-01-10 08:00:00',
            'Dr. House',
            'AUTH-55',
            'ALLY-9',
            '0',
            [
                new LabOrderDetailDto('REQ-123', 'ADM-1', 'C123', 'Lab A', null, null, null, null, null, null, null, null),
                new LabOrderDetailDto('REQ-123', 'ADM-1', 'C124', 'Lab B', null, null, null, null, null, null, null, null),
            ]
        );

        $orderRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (LabOrder $order) => count($order->getDetails()) === 2));

        $detailRepository
            ->expects($this->once())
            ->method('saveMany')
            ->with($this->callback(fn (array $details) => count($details) === 2));

        $useCase = new CreateLabOrderUseCase($orderRepository, $detailRepository);
        $order = $useCase->execute($dto);

        $this->assertSame('REQ-123', $order->getIdSolicitudKey());
        $this->assertCount(2, $order->getDetails());
        $this->assertSame('pending', $order->getEstadoDeLaOrden());
    }
}
