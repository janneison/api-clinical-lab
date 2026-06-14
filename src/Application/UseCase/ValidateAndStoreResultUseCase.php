<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Application\Dto\LabResultDto;
use ClinicalLab\Domain\Entity\Antibiograma;
use ClinicalLab\Domain\Entity\AntibiogramaItem;
use ClinicalLab\Domain\Entity\ExamParameter;
use ClinicalLab\Domain\Entity\LabResult;
use ClinicalLab\Domain\Entity\LabResultValue;
use ClinicalLab\Domain\Repository\AntibiogramaRepositoryInterface;
use ClinicalLab\Domain\Repository\ExamParameterRangeRepositoryInterface;
use ClinicalLab\Domain\Repository\ExamParameterRepositoryInterface;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use ClinicalLab\Domain\Repository\LabResultRepositoryInterface;
use ClinicalLab\Domain\Repository\LabResultValueRepositoryInterface;
use DateTimeImmutable;
use RuntimeException;

class ValidateAndStoreResultUseCase
{
    public function __construct(
        private readonly LabOrderRepositoryInterface           $orderRepository,
        private readonly LabResultRepositoryInterface          $resultRepository,
        private readonly ExamParameterRepositoryInterface      $parameterRepository,
        private readonly LabResultValueRepositoryInterface     $resultValueRepository,
        private readonly ExamParameterRangeRepositoryInterface $rangeRepository,
        private readonly AntibiogramaRepositoryInterface       $antibiogramaRepository,
    ) {
    }

    public function execute(LabResultDto $dto): void
    {
        // 1. Verificar que la orden existe
        $order = $this->orderRepository->findByIdSolicitudKey($dto->idSolicitudKey);
        if (!$order) {
            throw new RuntimeException('Orden no encontrada');
        }

        // 2. Obtener parámetros configurados para este CUPS
        $parameters = $this->parameterRepository->findByCups($dto->cups);

        if (!empty($parameters)) {
            $this->validateStructuredValues($dto, $parameters);
        } else {
            $this->assertResultadoField($dto);
        }

        // 3. Guardar resultado base (values_json retrocompatible)
        $result = new LabResult(
            $dto->idSolicitudKey,
            $dto->cups,
            $dto->values,
            $dto->attachmentPath,
            new DateTimeImmutable(),
            $dto->bacteriologoId,
        );

        $labResultId = $this->resultRepository->save($result);

        // 4. Guardar valores estructurados con flags
        if (!empty($parameters)) {
            $this->saveStructuredValues($labResultId, $dto->values, $parameters);
        }

        // 5. Guardar antibiogramas si vienen en el request
        if (!empty($dto->antibiogramas)) {
            foreach ($dto->antibiogramas as $abDto) {
                $ab = new Antibiograma(
                    0,
                    $labResultId,
                    $abDto->bacteriaAislada,
                    $abDto->gram,
                    $abDto->tiempoIncubacion,
                    $abDto->gramOrina,
                    $abDto->observaciones,
                );
                foreach ($abDto->items as $itemDto) {
                    $ab->addItem(new AntibiogramaItem(
                        0,
                        0, // se asigna en el repositorio
                        $itemDto->antibiotico,
                        $itemDto->cim,
                        $itemDto->sensibilidad,
                        $itemDto->metodo,
                    ));
                }
                $this->antibiogramaRepository->save($ab);
            }
        }

        // 6. Marcar la orden como completada
        $order->updateProgress(100);
        $this->orderRepository->update($order);
    }

    // ── Validación ────────────────────────────────────────────────────────────

    /** @param ExamParameter[] $parameters */
    private function validateStructuredValues(LabResultDto $dto, array $parameters): void
    {
        $missing = [];
        foreach ($parameters as $param) {
            if ($param->isObligatorio() && !array_key_exists($param->getCodigo(), $dto->values)) {
                $missing[] = $param->getCodigo() . ' (' . $param->getNombre() . ')';
            }
        }

        if (!empty($missing)) {
            throw new RuntimeException('Faltan parámetros obligatorios: ' . implode(', ', $missing));
        }
    }

    private function assertResultadoField(LabResultDto $dto): void
    {
        if (!isset($dto->values['resultado'])) {
            throw new RuntimeException('El resultado debe incluir un valor de resultado principal.');
        }
    }

    // ── Persistencia estructurada ─────────────────────────────────────────────

    /** @param ExamParameter[] $parameters */
    private function saveStructuredValues(int $labResultId, array $values, array $parameters): void
    {
        // Indexar por código (puede haber varios por sexo; tomamos el primero como referencia)
        $paramMap = [];
        foreach ($parameters as $param) {
            if (!isset($paramMap[$param->getCodigo()])) {
                $paramMap[$param->getCodigo()] = $param;
            }
        }

        foreach ($values as $codigo => $rawValue) {
            if (!isset($paramMap[$codigo])) {
                continue; // valor extra → queda en values_json, no en tabla estructurada
            }

            $param = $paramMap[$codigo];

            // El valor puede venir como escalar o como array ['valor' => ..., 'unidad' => ...]
            $rawScalar = is_array($rawValue) ? ($rawValue['valor'] ?? null) : $rawValue;
            $reactivo  = is_array($rawValue) ? ($rawValue['reactivo'] ?? null) : null;

            [$valorNumerico, $valorTexto, $valorBooleano, $flag] =
                $this->resolveValue($param, $rawScalar, $reactivo);

            $this->resultValueRepository->save(new LabResultValue(
                0,
                $labResultId,
                $param->getId(),
                $valorNumerico,
                $valorTexto,
                $valorBooleano,
                $flag,
                $reactivo,
            ));
        }
    }

    /**
     * Resuelve el valor y calcula el flag según el tipo del parámetro.
     *
     * @return array{0: ?float, 1: ?string, 2: ?bool, 3: string}
     *         [valorNumerico, valorTexto, valorBooleano, flag]
     */
    private function resolveValue(
        ExamParameter $param,
        mixed         $rawScalar,
        ?string       $reactivo
    ): array {
        return match ($param->getTipoResultado()) {
            ExamParameter::TIPO_NUMERICO  => $this->resolveNumerico($param, $rawScalar, $reactivo),
            ExamParameter::TIPO_BOOLEANO  => $this->resolveBooleano($param, $rawScalar),
            default                       => $this->resolveTexto($rawScalar),   // TIPO_TEXTO
        };
    }

    /** @return array{0: ?float, 1: null, 2: null, 3: string} */
    private function resolveNumerico(ExamParameter $param, mixed $raw, ?string $reactivo): array
    {
        if ($raw === null || !is_numeric($raw)) {
            return [null, (string) $raw, null, 'indeterminado'];
        }

        $valor = (float) $raw;
        $flag  = $this->calcularFlagNumerico($param, $valor, $reactivo);

        return [$valor, null, null, $flag];
    }

    private function calcularFlagNumerico(ExamParameter $param, float $valor, ?string $reactivo): string
    {
        // Prioridad 1: rango específico por reactivo
        if ($reactivo !== null) {
            $ranges = $this->rangeRepository->findByParameter($param->getId(), $reactivo);
            if (!empty($ranges)) {
                return $ranges[0]->calcularFlag($valor);
            }
        }

        // Prioridad 2: rango del parámetro (fallback)
        return $param->calcularFlag($valor);
    }

    /** @return array{0: null, 1: null, 2: bool, 3: string} */
    private function resolveBooleano(ExamParameter $param, mixed $raw): array
    {
        // Acepta: true/false, 1/0, "true"/"false", "1"/"0", "si"/"no", "yes"/"no"
        $bool = $this->parseBool($raw);

        if ($bool === null) {
            return [null, (string) $raw, null, 'indeterminado'];
        }

        $flag = $param->calcularFlagBooleano($bool);
        return [null, null, $bool, $flag];
    }

    /** @return array{0: null, 1: string, 2: null, 3: string} */
    private function resolveTexto(mixed $raw): array
    {
        return [null, (string) $raw, null, 'indeterminado'];
    }

    private function parseBool(mixed $value): ?bool
    {
        if (is_bool($value)) return $value;
        if (is_int($value))  return $value !== 0;

        $str = strtolower(trim((string) $value));

        return match ($str) {
            'true',  '1', 'si',  'yes', 'positivo',   'reactivo'    => true,
            'false', '0', 'no',  'negativo', 'no_reactivo'           => false,
            default => null,
        };
    }
}
