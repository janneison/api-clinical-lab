<?php

declare(strict_types=1);

namespace Tests\Unit;

use ClinicalLab\Application\UseCase\SendLabOrderUseCase;
use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Entity\LabOrderDetail;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use ClinicalLab\Domain\Service\ExternalLabClientInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SendLabOrderUseCaseTest extends TestCase
{
    public function testSendsOrderAndUpdatesStatus(): void
    {
        $order = new LabOrder(
            'REQ-123',
            'ADM-1',
            'ATT-1',
            'CC',
            '100200300',
            'Paciente Demo',
            'M',
            new DateTimeImmutable('1980-01-02'),
            'Hospital Central',
            new DateTimeImmutable('2024-01-10 08:00:00'),
            'Dr. House',
            'AUTH-55',
            'ALLY-9',
            null,
            0.0
        );
        $order->addDetail(new LabOrderDetail('REQ-123', 'ADM-1', 'C123', 'Lab A', null, null, null, null, null, null, null, null));

        $orderRepository = $this->createMock(LabOrderRepositoryInterface::class);
        $orderRepository->expects($this->once())
            ->method('findByIdSolicitudKey')
            ->with('REQ-123')
            ->willReturn($order);
        $orderRepository->expects($this->once())
            ->method('update')
            ->with($this->callback(fn (LabOrder $updated) => $updated->getEstadoDeLaOrden() === LabOrder::STATUS_SENT));

        $externalClient = $this->createMock(ExternalLabClientInterface::class);
        $externalClient->expects($this->once())
            ->method('sendOrder')
            ->with($order, $order->getDetails());

        $useCase = new SendLabOrderUseCase($orderRepository, $externalClient);
        $useCase->execute('REQ-123');

        $this->assertSame(LabOrder::STATUS_SENT, $order->getEstadoDeLaOrden());
        $this->assertInstanceOf(DateTimeImmutable::class, $order->getFechaEnvio());
    }

    public function testThrowsWhenOrderNotFound(): void
    {
        $orderRepository = $this->createMock(LabOrderRepositoryInterface::class);
        $orderRepository->method('findByIdSolicitudKey')->willReturn(null);

        $externalClient = $this->createMock(ExternalLabClientInterface::class);
        $externalClient->expects($this->never())->method('sendOrder');

        $useCase = new SendLabOrderUseCase($orderRepository, $externalClient);

        $this->expectException(RuntimeException::class);
        $useCase->execute('MISSING');
    }
}
