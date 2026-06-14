<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Application\Dto\ExamParameterDto;
use ClinicalLab\Application\Dto\ExamTypeDto;
use ClinicalLab\Domain\Entity\ExamParameter;
use ClinicalLab\Domain\Entity\ExamType;
use ClinicalLab\Domain\Repository\ExamParameterRepositoryInterface;
use ClinicalLab\Domain\Repository\ExamTypeRepositoryInterface;
use RuntimeException;

class ExamCatalogUseCase
{
    public function __construct(
        private readonly ExamTypeRepositoryInterface      $examTypeRepo,
        private readonly ExamParameterRepositoryInterface $parameterRepo,
    ) {
    }

    // ── Tipos de examen ───────────────────────────────────────────────────────

    /** @return ExamType[] */
    public function listExamTypes(bool $soloActivos = true): array
    {
        return $this->examTypeRepo->findAll($soloActivos);
    }

    public function createExamType(ExamTypeDto $dto): void
    {
        if ($this->examTypeRepo->findByCups($dto->cups)) {
            throw new RuntimeException("Ya existe un tipo de examen con CUPS: {$dto->cups}");
        }

        $this->examTypeRepo->save(
            new ExamType($dto->cups, $dto->nombre, $dto->descripcion, $dto->activo)
        );
    }

    public function updateExamType(ExamTypeDto $dto): void
    {
        if (!$this->examTypeRepo->findByCups($dto->cups)) {
            throw new RuntimeException("Tipo de examen no encontrado: {$dto->cups}");
        }

        $this->examTypeRepo->update(
            new ExamType($dto->cups, $dto->nombre, $dto->descripcion, $dto->activo)
        );
    }

    // ── Parámetros ────────────────────────────────────────────────────────────

    /** @return ExamParameter[] */
    public function listParameters(string $cups): array
    {
        if (!$this->examTypeRepo->findByCups($cups)) {
            throw new RuntimeException("Tipo de examen no encontrado: {$cups}");
        }

        return $this->parameterRepo->findByCups($cups);
    }

    public function addParameter(ExamParameterDto $dto): int
    {
        if (!$this->examTypeRepo->findByCups($dto->cups)) {
            throw new RuntimeException("Tipo de examen no encontrado: {$dto->cups}");
        }

        $this->validateSexo($dto->sexo);
        $this->validateTipoResultado($dto->tipoResultado, $dto->etiquetaBooleano);

        return $this->parameterRepo->save(new ExamParameter(
            0,
            $dto->cups,
            $dto->codigo,
            $dto->nombre,
            $dto->unidad,
            $dto->valorMinRef,
            $dto->valorMaxRef,
            $dto->sexo,
            $dto->edadMin,
            $dto->edadMax,
            $dto->obligatorio,
            $dto->orden,
            true,
            $dto->tipoResultado,
            $dto->etiquetaBooleano,
            $dto->comentario,
        ));
    }

    public function updateParameter(int $id, ExamParameterDto $dto): void
    {
        $existing = $this->parameterRepo->findById($id);
        if (!$existing) {
            throw new RuntimeException("Parámetro no encontrado: {$id}");
        }

        $this->validateSexo($dto->sexo);
        $this->validateTipoResultado($dto->tipoResultado, $dto->etiquetaBooleano);

        $this->parameterRepo->update(new ExamParameter(
            $id,
            $dto->cups,
            $dto->codigo,
            $dto->nombre,
            $dto->unidad,
            $dto->valorMinRef,
            $dto->valorMaxRef,
            $dto->sexo,
            $dto->edadMin,
            $dto->edadMax,
            $dto->obligatorio,
            $dto->orden,
            true,
            $dto->tipoResultado,
            $dto->etiquetaBooleano,
            $dto->comentario,
        ));
    }

    public function deactivateParameter(int $id): void
    {
        if (!$this->parameterRepo->findById($id)) {
            throw new RuntimeException("Parámetro no encontrado: {$id}");
        }

        $this->parameterRepo->deactivate($id);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validateSexo(string $sexo): void
    {
        if (!in_array($sexo, ['M', 'F', '*'], true)) {
            throw new RuntimeException("Valor de sexo inválido: '{$sexo}'. Use 'M', 'F' o '*'.");
        }
    }

    private function validateTipoResultado(string $tipo, ?string $etiqueta): void
    {
        $tiposValidos = [
            \ClinicalLab\Domain\Entity\ExamParameter::TIPO_NUMERICO,
            \ClinicalLab\Domain\Entity\ExamParameter::TIPO_TEXTO,
            \ClinicalLab\Domain\Entity\ExamParameter::TIPO_BOOLEANO,
        ];

        if (!in_array($tipo, $tiposValidos, true)) {
            throw new RuntimeException(
                "Tipo de resultado inválido: '{$tipo}'. Use: " . implode(', ', $tiposValidos)
            );
        }

        if ($tipo === \ClinicalLab\Domain\Entity\ExamParameter::TIPO_BOOLEANO) {
            $etiquetasValidas = [
                \ClinicalLab\Domain\Entity\ExamParameter::ETIQUETA_NORMAL_ALTO,
                \ClinicalLab\Domain\Entity\ExamParameter::ETIQUETA_POSITIVO_NEGATIVO,
                \ClinicalLab\Domain\Entity\ExamParameter::ETIQUETA_REACTIVO_NO_REACTIVO,
            ];
            if ($etiqueta === null || !in_array($etiqueta, $etiquetasValidas, true)) {
                throw new RuntimeException(
                    "Para tipo 'booleano' se requiere etiquetaBooleano. Valores válidos: "
                    . implode(', ', $etiquetasValidas)
                );
            }
        }
    }
}
