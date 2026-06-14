<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Application\Dto\ExamParameterDto;
use ClinicalLab\Application\Dto\ExamTypeDto;
use ClinicalLab\Application\UseCase\ExamCatalogUseCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

class ExamCatalogController
{
    public function __construct(private readonly ExamCatalogUseCase $useCase)
    {
    }

    // ── Tipos de examen ───────────────────────────────────────────────────────

    public function listTypes(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params     = $request->getQueryParams();
        $soloActivos = ($params['activo'] ?? '1') !== '0';

        $types = $this->useCase->listExamTypes($soloActivos);

        $data = array_map(fn($t) => [
            'cups'        => $t->getCups(),
            'nombre'      => $t->getNombre(),
            'descripcion' => $t->getDescripcion(),
            'activo'      => $t->isActivo(),
        ], $types);

        return $this->json($response, $data);
    }

    public function createType(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        foreach (['cups', 'nombre'] as $field) {
            if (empty($body[$field])) {
                return $this->json($response, ['error' => "Campo requerido: {$field}"], 422);
            }
        }

        try {
            $this->useCase->createExamType(new ExamTypeDto(
                cups:        trim($body['cups']),
                nombre:      trim($body['nombre']),
                descripcion: $body['descripcion'] ?? null,
                activo:      (bool) ($body['activo'] ?? true),
            ));

            return $this->json($response, ['message' => 'Tipo de examen creado'], 201);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 422);
        }
    }

    public function updateType(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = $request->getParsedBody();

        foreach (['nombre'] as $field) {
            if (empty($body[$field])) {
                return $this->json($response, ['error' => "Campo requerido: {$field}"], 422);
            }
        }

        try {
            $this->useCase->updateExamType(new ExamTypeDto(
                cups:        $args['cups'],
                nombre:      trim($body['nombre']),
                descripcion: $body['descripcion'] ?? null,
                activo:      (bool) ($body['activo'] ?? true),
            ));

            return $this->json($response, ['message' => 'Tipo de examen actualizado']);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'no encontrado') ? 404 : 422;
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }
    }

    // ── Parámetros ────────────────────────────────────────────────────────────

    public function listParameters(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $params = $this->useCase->listParameters($args['cups']);

            $data = array_map(fn($p) => [
                'id'               => $p->getId(),
                'codigo'           => $p->getCodigo(),
                'nombre'           => $p->getNombre(),
                'unidad'           => $p->getUnidad(),
                'valorMinRef'      => $p->getValorMinRef(),
                'valorMaxRef'      => $p->getValorMaxRef(),
                'sexo'             => $p->getSexo(),
                'edadMin'          => $p->getEdadMin(),
                'edadMax'          => $p->getEdadMax(),
                'obligatorio'      => $p->isObligatorio(),
                'orden'            => $p->getOrden(),
                'tipoResultado'    => $p->getTipoResultado(),
                'etiquetaBooleano' => $p->getEtiquetaBooleano(),
                'comentario'       => $p->getComentario(),
                'activo'           => $p->isActivo(),
            ], $params);

            return $this->json($response, $data);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 404);
        }
    }

    public function addParameter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = $request->getParsedBody();

        foreach (['codigo', 'nombre'] as $field) {
            if (empty($body[$field])) {
                return $this->json($response, ['error' => "Campo requerido: {$field}"], 422);
            }
        }

        try {
            $id = $this->useCase->addParameter(new ExamParameterDto(
                cups:             $args['cups'],
                codigo:           trim($body['codigo']),
                nombre:           trim($body['nombre']),
                unidad:           $body['unidad']      ?? null,
                valorMinRef:      isset($body['valorMinRef']) ? (float) $body['valorMinRef'] : null,
                valorMaxRef:      isset($body['valorMaxRef']) ? (float) $body['valorMaxRef'] : null,
                sexo:             $body['sexo']        ?? '*',
                edadMin:          isset($body['edadMin']) ? (int) $body['edadMin'] : null,
                edadMax:          isset($body['edadMax']) ? (int) $body['edadMax'] : null,
                obligatorio:      (bool) ($body['obligatorio'] ?? false),
                orden:            (int)  ($body['orden']       ?? 0),
                tipoResultado:    $body['tipoResultado']    ?? 'numerico',
                etiquetaBooleano: $body['etiquetaBooleano'] ?? null,
                comentario:       $body['comentario']       ?? null,
            ));

            return $this->json($response, ['id' => $id, 'message' => 'Parámetro creado'], 201);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'no encontrado') ? 404 : 422;
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }
    }

    public function updateParameter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = $request->getParsedBody();

        foreach (['codigo', 'nombre'] as $field) {
            if (empty($body[$field])) {
                return $this->json($response, ['error' => "Campo requerido: {$field}"], 422);
            }
        }

        try {
            $this->useCase->updateParameter((int) $args['id'], new ExamParameterDto(
                cups:             $args['cups'],
                codigo:           trim($body['codigo']),
                nombre:           trim($body['nombre']),
                unidad:           $body['unidad']      ?? null,
                valorMinRef:      isset($body['valorMinRef']) ? (float) $body['valorMinRef'] : null,
                valorMaxRef:      isset($body['valorMaxRef']) ? (float) $body['valorMaxRef'] : null,
                sexo:             $body['sexo']        ?? '*',
                edadMin:          isset($body['edadMin']) ? (int) $body['edadMin'] : null,
                edadMax:          isset($body['edadMax']) ? (int) $body['edadMax'] : null,
                obligatorio:      (bool) ($body['obligatorio'] ?? false),
                orden:            (int)  ($body['orden']       ?? 0),
                tipoResultado:    $body['tipoResultado']    ?? 'numerico',
                etiquetaBooleano: $body['etiquetaBooleano'] ?? null,
                comentario:       $body['comentario']       ?? null,
            ));

            return $this->json($response, ['message' => 'Parámetro actualizado']);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'no encontrado') ? 404 : 422;
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }
    }

    public function deactivateParameter(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $this->useCase->deactivateParameter((int) $args['id']);
            return $this->json($response, ['message' => 'Parámetro desactivado']);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 404);
        }
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
