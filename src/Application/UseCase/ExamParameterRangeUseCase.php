<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Application\Dto\ExamParameterRangeDto;
use ClinicalLab\Domain\Entity\ExamParameterRange;
use ClinicalLab\Domain\Repository\ExamParameterRangeRepositoryInterface;
use ClinicalLab\Domain\Repository\ExamParameterRepositoryInterface;
use RuntimeException;

class ExamParameterRangeUseCase
{
    public function __construct(
        private readonly ExamParameterRangeRepositoryInterface $rangeRepo,
        private readonly ExamParameterRepositoryInterface      $paramRepo,
    ) {
    }

    /** @return ExamParameterRange[] */
    public function list(int $parameterId): array
    {
        $this->assertParameterExists($parameterId);
        return $this->rangeRepo->findByParameter($parameterId);
    }

    public function add(ExamParameterRangeDto $dto): int
    {
        $param = $this->assertParameterExists($dto->parameterId);

        if ($param->getTipoResultado() !== 'numerico') {
            throw new RuntimeException(
                'Los rangos por reactivo solo aplican a parámetros de tipo numérico.'
            );
        }

        $this->validateSexo($dto->sexo);

        return $this->rangeRepo->save(new ExamParameterRange(
            0,
            $dto->parameterId,
            $dto->reactivo,
            $dto->valorMinRef,
            $dto->valorMaxRef,
            $dto->sexo,
            $dto->edadMin,
            $dto->edadMax,
            true
        ));
    }

    public function update(int $rangeId, ExamParameterRangeDto $dto): void
    {
        if (!$this->rangeRepo->findById($rangeId)) {
            throw new RuntimeException("Rango no encontrado: {$rangeId}");
        }

        $this->validateSexo($dto->sexo);

        $this->rangeRepo->update(new ExamParameterRange(
            $rangeId,
            $dto->parameterId,
            $dto->reactivo,
            $dto->valorMinRef,
            $dto->valorMaxRef,
            $dto->sexo,
            $dto->edadMin,
            $dto->edadMax,
            true
        ));
    }

    public function deactivate(int $rangeId): void
    {
        if (!$this->rangeRepo->findById($rangeId)) {
            throw new RuntimeException("Rango no encontrado: {$rangeId}");
        }
        $this->rangeRepo->deactivate($rangeId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function assertParameterExists(int $parameterId): \ClinicalLab\Domain\Entity\ExamParameter
    {
        $param = $this->paramRepo->findById($parameterId);
        if (!$param) {
            throw new RuntimeException("Parámetro no encontrado: {$parameterId}");
        }
        return $param;
    }

    private function validateSexo(string $sexo): void
    {
        if (!in_array($sexo, ['M', 'F', '*'], true)) {
            throw new RuntimeException("Valor de sexo inválido: '{$sexo}'. Use 'M', 'F' o '*'.");
        }
    }
}
