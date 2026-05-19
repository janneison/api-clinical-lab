<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Application\Dto\HealthCenterDto;
use ClinicalLab\Application\UseCase\HealthCenterUseCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class HealthCenterController
{
    public function __construct(private readonly HealthCenterUseCase $useCase)
    {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params      = $request->getQueryParams();
        $soloActivos = ($params['activo'] ?? '1') !== '0';
        $aliadoId    = $params['aliado_id'] ?? null;

        $centers = $this->useCase->list($soloActivos, $aliadoId ?: null);

        return $this->json($response, array_map(fn($c) => [
            'id'        => $c->getId(),
            'nombre'    => $c->getNombre(),
            'ciudad'    => $c->getCiudad(),
            'direccion' => $c->getDireccion(),
            'telefono'  => $c->getTelefono(),
            'activo'    => $c->isActivo(),
        ], $centers));
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (empty($body['nombre'])) {
            return $this->json($response, ['error' => 'Campo requerido: nombre'], 422);
        }

        $id = $this->useCase->create(new HealthCenterDto(
            nombre:    trim($body['nombre']),
            ciudad:    $body['ciudad']    ?? null,
            direccion: $body['direccion'] ?? null,
            telefono:  $body['telefono']  ?? null,
            activo:    (bool) ($body['activo'] ?? true),
        ));

        return $this->json($response, ['id' => $id, 'message' => 'Centro de salud creado'], 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (empty($body['nombre'])) {
            return $this->json($response, ['error' => 'Campo requerido: nombre'], 422);
        }

        try {
            $this->useCase->update((int) $args['id'], new HealthCenterDto(
                nombre:    trim($body['nombre']),
                ciudad:    $body['ciudad']    ?? null,
                direccion: $body['direccion'] ?? null,
                telefono:  $body['telefono']  ?? null,
                activo:    (bool) ($body['activo'] ?? true),
            ));
            return $this->json($response, ['message' => 'Centro de salud actualizado']);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 404);
        }
    }

    public function associateAliado(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $this->useCase->associateAliado((int) $args['id'], $args['aliadoId']);
            return $this->json($response, ['message' => 'Aliado asociado al centro de salud']);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 404);
        }
    }

    public function dissociateAliado(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $this->useCase->dissociateAliado((int) $args['id'], $args['aliadoId']);
            return $this->json($response, ['message' => 'Aliado desasociado del centro de salud']);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 404);
        }
    }

    private function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
