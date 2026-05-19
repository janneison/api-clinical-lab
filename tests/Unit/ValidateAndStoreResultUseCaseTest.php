<?php

declare(strict_types=1);

namespace Tests\Unit;

use ClinicalLab\Application\Dto\LabResultDto;
use ClinicalLab\Application\UseCase\ValidateAndStoreResultUseCase;
use ClinicalLab\Domain\Entity\ExamParameter;
use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Repository\ExamParameterRepositoryInterface;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use ClinicalLab\Domain\Repository\LabResultRepositoryInterface;
use ClinicalLab\Domain\Repository\LabResultValueRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ValidateAndStoreResultUseCaseTest extends TestCase
{
    private function makeOrder(): LabOrder
    {
        return new LabOrder(
            'REQ-123', 'ADM-1', null, 'CC', '100200300',
            'Paciente Demo', 'M',
            new DateTimeImmutable('1980-01-02'),
            'Hospital Central',
            new DateTimeImmutable('2024-01-10 08:00:00'),
            'Dr. House', null, null, null, 0.0
        );
    }

    private function makeParam(string $codigo, float $min, float $max, bool $obligatorio = true): ExamParameter
    {
        return new ExamParameter(1, '903820', $codigo, ucfirst($codigo), 'g/dL', $min, $max, '*', null, null, $obligatorio, 1, true);
    }

    private function makeUseCase(
        LabOrderRepositoryInterface       $orderRepo,
        LabResultRepositoryInterface      $resultRepo,
        ExamParameterRepositoryInterface  $paramRepo,
        LabResultValueRepositoryInterface $valueRepo
    ): ValidateAndStoreResultUseCase {
        return new ValidateAndStoreResultUseCase($orderRepo, $resultRepo, $paramRepo, $valueRepo);
    }

    // ── Examen libre (sin parámetros configurados) ────────────────────────────

    public function testStoresFreeResultAndCompletesOrder(): void
    {
        $order = $this->makeOrder();

        $orderRepo  = $this->createMock(LabOrderRepositoryInterface::class);
        $orderRepo->method('findByIdSolicitudKey')->willReturn($order);
        $orderRepo->expects($this->once())->method('update')
            ->with($this->callback(fn(LabOrder $o) => $o->getPorcEjecucion() === 100.0));

        $resultRepo = $this->createMock(LabResultRepositoryInterface::class);
        $resultRepo->expects($this->once())->method('save')->willReturn(1);

        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);
        $paramRepo->method('findByCups')->willReturn([]);   // sin parámetros → examen libre

        $valueRepo = $this->createMock(LabResultValueRepositoryInterface::class);
        $valueRepo->expects($this->never())->method('save');

        $dto = new LabResultDto('REQ-123', 'C999', ['resultado' => 'Normal'], null);

        $this->makeUseCase($orderRepo, $resultRepo, $paramRepo, $valueRepo)->execute($dto);

        $this->assertSame('completed', $order->getEstadoDeLaOrden());
    }

    public function testThrowsWhenFreeResultMissingResultadoField(): void
    {
        $orderRepo = $this->createMock(LabOrderRepositoryInterface::class);
        $orderRepo->method('findByIdSolicitudKey')->willReturn($this->makeOrder());

        $resultRepo = $this->createMock(LabResultRepositoryInterface::class);
        $resultRepo->expects($this->never())->method('save');

        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);
        $paramRepo->method('findByCups')->willReturn([]);

        $valueRepo = $this->createMock(LabResultValueRepositoryInterface::class);

        $dto = new LabResultDto('REQ-123', 'C999', ['glucosa' => 110], null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/resultado principal/');

        $this->makeUseCase($orderRepo, $resultRepo, $paramRepo, $valueRepo)->execute($dto);
    }

    // ── Examen parametrizado ──────────────────────────────────────────────────

    public function testStoresStructuredResultWithFlags(): void
    {
        $order = $this->makeOrder();
        $param = $this->makeParam('hb', 13.5, 17.5, true);

        $orderRepo = $this->createMock(LabOrderRepositoryInterface::class);
        $orderRepo->method('findByIdSolicitudKey')->willReturn($order);
        $orderRepo->expects($this->once())->method('update');

        $resultRepo = $this->createMock(LabResultRepositoryInterface::class);
        $resultRepo->expects($this->once())->method('save')->willReturn(10);

        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);
        $paramRepo->method('findByCups')->willReturn([$param]);

        $valueRepo = $this->createMock(LabResultValueRepositoryInterface::class);
        $valueRepo->expects($this->once())->method('save')
            ->with($this->callback(fn($v) =>
                $v->getValorNumerico() === 14.5 &&
                $v->getFlag() === 'normal'
            ));

        $dto = new LabResultDto('REQ-123', '903820', ['hb' => '14.5'], null);

        $this->makeUseCase($orderRepo, $resultRepo, $paramRepo, $valueRepo)->execute($dto);
    }

    public function testStructuredResultCalculatesFlagAlto(): void
    {
        $order = $this->makeOrder();
        $param = $this->makeParam('hb', 13.5, 17.5, true);

        $orderRepo = $this->createMock(LabOrderRepositoryInterface::class);
        $orderRepo->method('findByIdSolicitudKey')->willReturn($order);
        $orderRepo->method('update');

        $resultRepo = $this->createMock(LabResultRepositoryInterface::class);
        $resultRepo->method('save')->willReturn(10);

        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);
        $paramRepo->method('findByCups')->willReturn([$param]);

        $valueRepo = $this->createMock(LabResultValueRepositoryInterface::class);
        $valueRepo->expects($this->once())->method('save')
            ->with($this->callback(fn($v) => $v->getFlag() === 'alto'));

        $dto = new LabResultDto('REQ-123', '903820', ['hb' => '18.0'], null);

        $this->makeUseCase($orderRepo, $resultRepo, $paramRepo, $valueRepo)->execute($dto);
    }

    public function testStructuredResultCalculatesFlagCritico(): void
    {
        $order = $this->makeOrder();
        $param = $this->makeParam('hb', 13.5, 17.5, true);

        $orderRepo = $this->createMock(LabOrderRepositoryInterface::class);
        $orderRepo->method('findByIdSolicitudKey')->willReturn($order);
        $orderRepo->method('update');

        $resultRepo = $this->createMock(LabResultRepositoryInterface::class);
        $resultRepo->method('save')->willReturn(10);

        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);
        $paramRepo->method('findByCups')->willReturn([$param]);

        $valueRepo = $this->createMock(LabResultValueRepositoryInterface::class);
        $valueRepo->expects($this->once())->method('save')
            ->with($this->callback(fn($v) => $v->getFlag() === 'critico'));

        // 9.0 < 13.5 * 0.7 = 9.45 → crítico
        $dto = new LabResultDto('REQ-123', '903820', ['hb' => '9.0'], null);

        $this->makeUseCase($orderRepo, $resultRepo, $paramRepo, $valueRepo)->execute($dto);
    }

    public function testThrowsWhenMandatoryParameterMissing(): void
    {
        $order = $this->makeOrder();
        $param = $this->makeParam('hb', 13.5, 17.5, obligatorio: true);

        $orderRepo = $this->createMock(LabOrderRepositoryInterface::class);
        $orderRepo->method('findByIdSolicitudKey')->willReturn($order);

        $resultRepo = $this->createMock(LabResultRepositoryInterface::class);
        $resultRepo->expects($this->never())->method('save');

        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);
        $paramRepo->method('findByCups')->willReturn([$param]);

        $valueRepo = $this->createMock(LabResultValueRepositoryInterface::class);

        // Enviamos 'wbc' pero el parámetro obligatorio es 'hb'
        $dto = new LabResultDto('REQ-123', '903820', ['wbc' => '7.2'], null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/obligatorios/');

        $this->makeUseCase($orderRepo, $resultRepo, $paramRepo, $valueRepo)->execute($dto);
    }

    public function testIgnoresExtraValuesNotInParameterConfig(): void
    {
        $order = $this->makeOrder();
        $param = $this->makeParam('hb', 13.5, 17.5, true);

        $orderRepo = $this->createMock(LabOrderRepositoryInterface::class);
        $orderRepo->method('findByIdSolicitudKey')->willReturn($order);
        $orderRepo->method('update');

        $resultRepo = $this->createMock(LabResultRepositoryInterface::class);
        $resultRepo->method('save')->willReturn(10);

        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);
        $paramRepo->method('findByCups')->willReturn([$param]);

        $valueRepo = $this->createMock(LabResultValueRepositoryInterface::class);
        // Solo debe guardarse 'hb', no 'extra_field'
        $valueRepo->expects($this->once())->method('save');

        $dto = new LabResultDto('REQ-123', '903820', ['hb' => '14.5', 'extra_field' => 'foo'], null);

        $this->makeUseCase($orderRepo, $resultRepo, $paramRepo, $valueRepo)->execute($dto);
    }

    public function testThrowsWhenOrderNotFound(): void
    {
        $orderRepo = $this->createMock(LabOrderRepositoryInterface::class);
        $orderRepo->method('findByIdSolicitudKey')->willReturn(null);

        $resultRepo = $this->createMock(LabResultRepositoryInterface::class);
        $resultRepo->expects($this->never())->method('save');

        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);
        $valueRepo = $this->createMock(LabResultValueRepositoryInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Orden no encontrada');

        $this->makeUseCase($orderRepo, $resultRepo, $paramRepo, $valueRepo)
            ->execute(new LabResultDto('MISSING', 'C123', ['resultado' => 'x'], null));
    }

    public function testHandlesArrayValueFormat(): void
    {
        // values puede venir como ['hb' => ['valor' => '14.5', 'unidad' => 'g/dL']]
        $order = $this->makeOrder();
        $param = $this->makeParam('hb', 13.5, 17.5, true);

        $orderRepo = $this->createMock(LabOrderRepositoryInterface::class);
        $orderRepo->method('findByIdSolicitudKey')->willReturn($order);
        $orderRepo->method('update');

        $resultRepo = $this->createMock(LabResultRepositoryInterface::class);
        $resultRepo->method('save')->willReturn(10);

        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);
        $paramRepo->method('findByCups')->willReturn([$param]);

        $valueRepo = $this->createMock(LabResultValueRepositoryInterface::class);
        $valueRepo->expects($this->once())->method('save')
            ->with($this->callback(fn($v) =>
                $v->getValorNumerico() === 14.5 &&
                $v->getFlag() === 'normal'
            ));

        $dto = new LabResultDto('REQ-123', '903820', [
            'hb' => ['valor' => '14.5', 'unidad' => 'g/dL', 'referencia' => '13.5-17.5'],
        ], null);

        $this->makeUseCase($orderRepo, $resultRepo, $paramRepo, $valueRepo)->execute($dto);
    }
}
