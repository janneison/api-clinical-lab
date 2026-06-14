<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Application\Dto\LabResultDto;
use ClinicalLab\Application\Dto\AntibiogramaDto;
use ClinicalLab\Application\Dto\AntibiogramaItemDto;
use ClinicalLab\Application\UseCase\ValidateAndStoreResultUseCase;
use ClinicalLab\Domain\Repository\AntibiogramaRepositoryInterface;
use ClinicalLab\Domain\Repository\BacteriologoRepositoryInterface;
use ClinicalLab\Domain\Repository\ExamParameterRepositoryInterface;
use ClinicalLab\Domain\Repository\LabResultRepositoryInterface;
use ClinicalLab\Domain\Repository\LabResultValueRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

class ResultController
{
    public function __construct(
        private readonly ValidateAndStoreResultUseCase     $useCase,
        private readonly LabResultRepositoryInterface      $resultRepository,
        private readonly LabResultValueRepositoryInterface $resultValueRepository,
        private readonly ExamParameterRepositoryInterface  $parameterRepository,
        private readonly BacteriologoRepositoryInterface   $bacteriologoRepository,
        private readonly AntibiogramaRepositoryInterface   $antibiogramaRepository,
    ) {
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        foreach (['idSolicitudKey', 'cups', 'values'] as $field) {
            if (empty($body[$field])) {
                return $this->json($response, ['error' => "Campo requerido faltante: $field"], 422);
            }
        }

        if (!is_array($body['values'])) {
            return $this->json($response, ['error' => 'El campo values debe ser un objeto JSON'], 422);
        }

        try {
            // Parsear antibiogramas si vienen en el body
            $antibiogramas = [];
            if (!empty($body['antibiogramas']) && is_array($body['antibiogramas'])) {
                foreach ($body['antibiogramas'] as $abRaw) {
                    if (empty($abRaw['bacteriaAislada'])) {
                        return $this->json($response, ['error' => 'Cada antibiograma requiere bacteriaAislada'], 422);
                    }
                    $items = [];
                    foreach ($abRaw['items'] ?? [] as $itemRaw) {
                        if (empty($itemRaw['antibiotico'])) {
                            return $this->json($response, ['error' => 'Cada item de antibiograma requiere antibiotico'], 422);
                        }
                        $sens = strtoupper(trim($itemRaw['sensibilidad'] ?? ''));
                        if ($sens !== '' && !in_array($sens, ['S', 'I', 'R'], true)) {
                            return $this->json($response, ['error' => "Sensibilidad inválida: {$sens}. Use S, I o R"], 422);
                        }
                        $items[] = new AntibiogramaItemDto(
                            $itemRaw['antibiotico'],
                            $itemRaw['cim']    ?? null,
                            $sens ?: null,
                            $itemRaw['metodo'] ?? null,
                        );
                    }
                    $antibiogramas[] = new AntibiogramaDto(
                        $abRaw['bacteriaAislada'],
                        $abRaw['gram']             ?? null,
                        $abRaw['tiempoIncubacion'] ?? null,
                        $abRaw['gramOrina']        ?? null,
                        $abRaw['observaciones']    ?? null,
                        $items,
                    );
                }
            }

            $dto = new LabResultDto(
                $body['idSolicitudKey'],
                $body['cups'],
                $body['values'],
                $body['attachmentPath'] ?? null,
                isset($body['bacteriologoId']) ? (int) $body['bacteriologoId'] : null,
                $antibiogramas,
            );

            $this->useCase->execute($dto);

            return $this->json($response, [
                'idSolicitudKey' => $body['idSolicitudKey'],
                'cups'           => $body['cups'],
                'message'        => 'Resultado registrado correctamente',
            ], 201);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'no encontrada') ? 404 : 422;
            return $this->json($response, ['error' => $e->getMessage()], $code);
        } catch (Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /orders/{id}/results
     * Devuelve los resultados estructurados de una orden, agrupados por CUPS.
     */
    public function getStructured(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $idSolicitudKey = $args['id'];
        $results        = $this->resultRepository->findAllByOrder($idSolicitudKey);

        if (empty($results)) {
            return $this->json($response, [
                'idSolicitudKey' => $idSolicitudKey,
                'resultados'     => [],
            ]);
        }

        $output = [];

        foreach ($results as $row) {
            $labResultId = (int) $row['id'];
            $cups        = $row['cups'];

            // Parámetros configurados para este CUPS
            $parameters = $this->parameterRepository->findByCups($cups);
            $paramMap   = [];
            foreach ($parameters as $p) {
                $paramMap[$p->getId()] = $p;
            }

            // Valores estructurados guardados
            $structuredValues = $this->resultValueRepository->findByLabResultId($labResultId);

            $valoresEstructurados = array_map(fn($v) => [
                'codigo'           => $paramMap[$v->getParameterId()]?->getCodigo() ?? (string) $v->getParameterId(),
                'nombre'           => $paramMap[$v->getParameterId()]?->getNombre() ?? '',
                'tipoResultado'    => $paramMap[$v->getParameterId()]?->getTipoResultado() ?? 'numerico',
                'valorNumerico'    => $v->getValorNumerico(),
                'valorTexto'       => $v->getValorTexto(),
                'valorBooleano'    => $v->getValorBooleano(),
                'reactivo'         => $v->getReactivo(),
                'unidad'           => $paramMap[$v->getParameterId()]?->getUnidad(),
                'valorMinRef'      => $paramMap[$v->getParameterId()]?->getValorMinRef(),
                'valorMaxRef'      => $paramMap[$v->getParameterId()]?->getValorMaxRef(),
                'etiquetaBooleano' => $paramMap[$v->getParameterId()]?->getEtiquetaBooleano(),
                'comentario'       => $paramMap[$v->getParameterId()]?->getComentario(),
                'flag'             => $v->getFlag(),
            ], $structuredValues);

            // Bacteriólogo que procesó este resultado
            $bacteriologoData = null;
            $bacteriologoId   = isset($row['bacteriologo_id']) ? (int) $row['bacteriologo_id'] : null;
            if ($bacteriologoId) {
                $bact = $this->bacteriologoRepository->findById($bacteriologoId);
                if ($bact) {
                    $bacteriologoData = [
                        'id'                  => $bact->getId(),
                        'nombre'              => $bact->getNombre(),
                        'tipoDocumento'       => $bact->getTipoDocumento(),
                        'identificacion'      => $bact->getIdentificacion(),
                        'tarjetaProfesional'  => $bact->getTarjetaProfesional(),
                        'universidad'         => $bact->getUniversidad(),
                        'firmaPath'           => $bact->getFirmaPath(),
                    ];
                }
            }

            // Antibiogramas (cultivos)
            $antibiogramasData = [];
            $antibiogramas = $this->antibiogramaRepository->findByLabResultId($labResultId);
            foreach ($antibiogramas as $ab) {
                $antibiogramasData[] = [
                    'id'               => $ab->getId(),
                    'bacteriaAislada'  => $ab->getBacteriaAislada(),
                    'esNegativo'       => $ab->isNegativo(),
                    'gram'             => $ab->getGram(),
                    'tiempoIncubacion' => $ab->getTiempoIncubacion(),
                    'gramOrina'        => $ab->getGramOrina(),
                    'observaciones'    => $ab->getObservaciones(),
                    'items'            => array_map(fn($item) => [
                        'antibiotico'  => $item->getAntibiotico(),
                        'cim'          => $item->getCim(),
                        'sensibilidad' => $item->getSensibilidad(),
                        'metodo'       => $item->getMetodo(),
                    ], $ab->getItems()),
                ];
            }

            $output[] = [
                'labResultId'          => $labResultId,
                'cups'                 => $cups,
                'bacteriologo'         => $bacteriologoData,
                'valuesJson'           => json_decode($row['values_json'], true),
                'valoresEstructurados' => $valoresEstructurados,
                'antibiogramas'        => $antibiogramasData,
                'receivedAt'           => $row['received_at'],
            ];
        }

        return $this->json($response, [
            'idSolicitudKey' => $idSolicitudKey,
            'resultados'     => $output,
        ]);
    }

    private function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
