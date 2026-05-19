<?php

declare(strict_types=1);

namespace Tests\Unit;

use ClinicalLab\Application\Dto\ExamParameterDto;
use ClinicalLab\Application\Dto\ExamTypeDto;
use ClinicalLab\Application\UseCase\ExamCatalogUseCase;
use ClinicalLab\Domain\Entity\ExamParameter;
use ClinicalLab\Domain\Entity\ExamType;
use ClinicalLab\Domain\Repository\ExamParameterRepositoryInterface;
use ClinicalLab\Domain\Repository\ExamTypeRepositoryInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExamCatalogUseCaseTest extends TestCase
{
    private function makeType(string $cups = '903820'): ExamType
    {
        return new ExamType($cups, 'Hemograma Completo', null, true);
    }

    private function makeParam(int $id = 1): ExamParameter
    {
        return new ExamParameter($id, '903820', 'wbc', 'Leucocitos', '10³/µL', 4.5, 11.0, '*', null, null, true, 1, true);
    }

    // ── listExamTypes ─────────────────────────────────────────────────────────

    public function testListExamTypesReturnsAll(): void
    {
        $typeRepo  = $this->createMock(ExamTypeRepositoryInterface::class);
        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);

        $typeRepo->expects($this->once())
            ->method('findAll')
            ->with(true)
            ->willReturn([$this->makeType()]);

        $useCase = new ExamCatalogUseCase($typeRepo, $paramRepo);
        $result  = $useCase->listExamTypes();

        $this->assertCount(1, $result);
        $this->assertSame('903820', $result[0]->getCups());
    }

    // ── createExamType ────────────────────────────────────────────────────────

    public function testCreatesExamTypeSuccessfully(): void
    {
        $typeRepo  = $this->createMock(ExamTypeRepositoryInterface::class);
        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);

        $typeRepo->method('findByCups')->willReturn(null);
        $typeRepo->expects($this->once())->method('save');

        $useCase = new ExamCatalogUseCase($typeRepo, $paramRepo);
        $useCase->createExamType(new ExamTypeDto('904010', 'Glucosa en Ayunas'));
    }

    public function testThrowsWhenCreatingDuplicateExamType(): void
    {
        $typeRepo  = $this->createMock(ExamTypeRepositoryInterface::class);
        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);

        $typeRepo->method('findByCups')->willReturn($this->makeType());
        $typeRepo->expects($this->never())->method('save');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Ya existe/');

        (new ExamCatalogUseCase($typeRepo, $paramRepo))
            ->createExamType(new ExamTypeDto('903820', 'Duplicado'));
    }

    // ── updateExamType ────────────────────────────────────────────────────────

    public function testUpdatesExamTypeSuccessfully(): void
    {
        $typeRepo  = $this->createMock(ExamTypeRepositoryInterface::class);
        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);

        $typeRepo->method('findByCups')->willReturn($this->makeType());
        $typeRepo->expects($this->once())->method('update');

        (new ExamCatalogUseCase($typeRepo, $paramRepo))
            ->updateExamType(new ExamTypeDto('903820', 'Hemograma Actualizado'));
    }

    public function testThrowsWhenUpdatingNonExistentExamType(): void
    {
        $typeRepo  = $this->createMock(ExamTypeRepositoryInterface::class);
        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);

        $typeRepo->method('findByCups')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no encontrado/');

        (new ExamCatalogUseCase($typeRepo, $paramRepo))
            ->updateExamType(new ExamTypeDto('GHOST', 'X'));
    }

    // ── listParameters ────────────────────────────────────────────────────────

    public function testListParametersReturnsParamsForExistingType(): void
    {
        $typeRepo  = $this->createMock(ExamTypeRepositoryInterface::class);
        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);

        $typeRepo->method('findByCups')->willReturn($this->makeType());
        $paramRepo->method('findByCups')->willReturn([$this->makeParam()]);

        $result = (new ExamCatalogUseCase($typeRepo, $paramRepo))->listParameters('903820');

        $this->assertCount(1, $result);
        $this->assertSame('wbc', $result[0]->getCodigo());
    }

    public function testThrowsWhenListingParamsForNonExistentType(): void
    {
        $typeRepo  = $this->createMock(ExamTypeRepositoryInterface::class);
        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);

        $typeRepo->method('findByCups')->willReturn(null);

        $this->expectException(RuntimeException::class);

        (new ExamCatalogUseCase($typeRepo, $paramRepo))->listParameters('GHOST');
    }

    // ── addParameter ──────────────────────────────────────────────────────────

    public function testAddsParameterSuccessfully(): void
    {
        $typeRepo  = $this->createMock(ExamTypeRepositoryInterface::class);
        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);

        $typeRepo->method('findByCups')->willReturn($this->makeType());
        $paramRepo->expects($this->once())->method('save')->willReturn(5);

        $id = (new ExamCatalogUseCase($typeRepo, $paramRepo))->addParameter(
            new ExamParameterDto('903820', 'hb', 'Hemoglobina', 'g/dL', 13.5, 17.5, 'M')
        );

        $this->assertSame(5, $id);
    }

    public function testThrowsWhenAddingParamWithInvalidSexo(): void
    {
        $typeRepo  = $this->createMock(ExamTypeRepositoryInterface::class);
        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);

        $typeRepo->method('findByCups')->willReturn($this->makeType());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/sexo/i');

        (new ExamCatalogUseCase($typeRepo, $paramRepo))->addParameter(
            new ExamParameterDto('903820', 'hb', 'Hemoglobina', 'g/dL', null, null, 'X')
        );
    }

    public function testThrowsWhenAddingParamToNonExistentType(): void
    {
        $typeRepo  = $this->createMock(ExamTypeRepositoryInterface::class);
        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);

        $typeRepo->method('findByCups')->willReturn(null);

        $this->expectException(RuntimeException::class);

        (new ExamCatalogUseCase($typeRepo, $paramRepo))->addParameter(
            new ExamParameterDto('GHOST', 'hb', 'Hemoglobina')
        );
    }

    // ── updateParameter ───────────────────────────────────────────────────────

    public function testUpdatesParameterSuccessfully(): void
    {
        $typeRepo  = $this->createMock(ExamTypeRepositoryInterface::class);
        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);

        $typeRepo->method('findByCups')->willReturn($this->makeType());
        $paramRepo->method('findById')->willReturn($this->makeParam());
        $paramRepo->expects($this->once())->method('update');

        (new ExamCatalogUseCase($typeRepo, $paramRepo))->updateParameter(
            1,
            new ExamParameterDto('903820', 'wbc', 'Leucocitos Actualizado', '10³/µL', 4.0, 12.0)
        );
    }

    public function testThrowsWhenUpdatingNonExistentParameter(): void
    {
        $typeRepo  = $this->createMock(ExamTypeRepositoryInterface::class);
        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);

        $typeRepo->method('findByCups')->willReturn($this->makeType());
        $paramRepo->method('findById')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no encontrado/');

        (new ExamCatalogUseCase($typeRepo, $paramRepo))->updateParameter(
            999,
            new ExamParameterDto('903820', 'wbc', 'X')
        );
    }

    // ── deactivateParameter ───────────────────────────────────────────────────

    public function testDeactivatesParameterSuccessfully(): void
    {
        $typeRepo  = $this->createMock(ExamTypeRepositoryInterface::class);
        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);

        $paramRepo->method('findById')->willReturn($this->makeParam());
        $paramRepo->expects($this->once())->method('deactivate')->with(1);

        (new ExamCatalogUseCase($typeRepo, $paramRepo))->deactivateParameter(1);
    }

    public function testThrowsWhenDeactivatingNonExistentParameter(): void
    {
        $typeRepo  = $this->createMock(ExamTypeRepositoryInterface::class);
        $paramRepo = $this->createMock(ExamParameterRepositoryInterface::class);

        $paramRepo->method('findById')->willReturn(null);

        $this->expectException(RuntimeException::class);

        (new ExamCatalogUseCase($typeRepo, $paramRepo))->deactivateParameter(999);
    }
}
