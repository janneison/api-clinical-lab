<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Application\Dto\LabResultDto;
use ClinicalLab\Application\UseCase\ValidateAndStoreResultUseCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

class ResultController
{
    public function __construct(private readonly ValidateAndStoreResultUseCase $useCase)
    {
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
            $dto = new LabResultDto(
                $body['idSolicitudKey'],
                $body['cups'],
                $body['values'],
                $body['attachmentPath'] ?? null
            );

            $this->useCase->execute($dto);

            return $this->json($response, [
                'idSolicitudKey' => $body['idSolicitudKey'],
                'cups' => $body['cups'],
                'message' => 'Resultado registrado correctamente',
            ], 201);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'no encontrada') ? 404 : 422;
            return $this->json($response, ['error' => $e->getMessage()], $code);
        } catch (Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
