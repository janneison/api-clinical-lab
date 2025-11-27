<?php

declare(strict_types=1);

namespace Tests\Unit;

use ClinicalLab\Application\Dto\LabResultDto;
use ClinicalLab\Application\UseCase\ValidateAndStoreResultUseCase;
use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use ClinicalLab\Domain\Repository\LabResultRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ValidateAndStoreResultUseCaseTest extends TestCase
{
    public function testStoresResultAndCompletesOrder(): void
    {
        $order = new LabOrder(
            'REQ-123',
            'ADM-1',
            null,
            'CC',
            '100200300',
            'Paciente Demo',
            'M',
            new DateTimeImmutable('1980-01-02'),
            'Hospital Central',
            new DateTimeImmutable('2024-01-10 08:00:00'),
            'Dr. House',
            null,
            null,
            null,
            0.0
        );

        $orderRepository = $this->createMock(LabOrderRepositoryInterface::class);
        $orderRepository->method('findByIdSolicitudKey')->willReturn($order);
        $orderRepository->expects($this->once())
            ->method('update')
            ->with($this->callback(fn (LabOrder $updated) => $updated->getPorcEjecucion() === 100.0));

        $resultRepository = $this->createMock(LabResultRepositoryInterface::class);
        $resultRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn ($result) => $result->getValues()['resultado'] === 'Negativo'));

        $dto = new LabResultDto('REQ-123', 'C123', ['resultado' => 'Negativo'], '/tmp/result.pdf');

        $useCase = new ValidateAndStoreResultUseCase($orderRepository, $resultRepository);
        $useCase->execute($dto);

        $this->assertSame('completed', $order->getEstadoDeLaOrden());
        $this->assertSame(100.0, $order->getPorcEjecucion());
    }

    public function testThrowsWhenMissingRequiredResult(): void
    {
        $orderRepository = $this->createMock(LabOrderRepositoryInterface::class);
        $orderRepository->method('findByIdSolicitudKey')->willReturn(new LabOrder(
            'REQ-123',
            'ADM-1',
            null,
            'CC',
            '100200300',
            'Paciente Demo',
            'M',
            new DateTimeImmutable('1980-01-02'),
            'Hospital Central',
            new DateTimeImmutable('2024-01-10 08:00:00'),
            'Dr. House',
            null,
            null,
            null,
            0.0
        ));

        $resultRepository = $this->createMock(LabResultRepositoryInterface::class);
        $resultRepository->expects($this->never())->method('save');

        $dto = new LabResultDto('REQ-123', 'C123', ['glucosa' => 110], null);

        $useCase = new ValidateAndStoreResultUseCase($orderRepository, $resultRepository);

        $this->expectException(RuntimeException::class);
        $useCase->execute($dto);
    }
}
