<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Application\Dto\ExamParameterRangeDto;
use ClinicalLab\Application\UseCase\ExamParameterRangeUseCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class ExamParameterRangeController
{
    public function __construct(private readonly ExamParameterRangeUseCase $useCase)
    {
    }

    public function list(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface {
        try {
            $ranges = $this->useCase->list((int) $args['parameterId']);

            $data = array_map(fn($r) => [
                'id'          => $r->getId(),
                'reactivo'    => $r->getReactivo(),
                'valorMinRef' => $r->getValorMinRef(),
                'valorMaxRef' => $r->getValorMaxRef(),
                'sexo'        => $r->getSexo(),
                'edadMin'     => $r->getEdadMin(),
                'edadMax'     => $r->getEdadMax(),
                'activo'      => $r->isActivo(),
            ], $ranges);

            return $this->json($response, $data);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 404);
        }
    }

    public function add(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface {
        $body = $request->getParsedBody();

        if (empty($body['reactivo'])) {
            return $this->json($response, ['error' => 'Campo requerido: reactivo'], 422);
        }

        try {
            $id = $this->useCase->add(new ExamParameterRangeDto(
                parameterId:  (int) $args['parameterId'],
                reactivo:     trim($body['reactivo']),
                valorMinRef:  isset($body['valorMinRef']) ? (float) $body['valorMinRef'] : null,
                valorMaxRef:  isset($body['valorMaxRef']) ? (float) $body['valorMaxRef'] : null,
                sexo:         $body['sexo']    ?? '*',
                edadMin:      isset($body['edadMin']) ? (int) $body['edadMin'] : null,
                edadMax:      isset($body['edadMax']) ? (int) $body['edadMax'] : null,
            ));

            return $this->json($response, ['id' => $id, 'message' => 'Rango creado'], 201);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'no encontrado') ? 404 : 422;
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }
    }

    public function update(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface {
        $body = $request->getParsedBody();

        if (empty($body['reactivo'])) {
            return $this->json($response, ['error' => 'Campo requerido: reactivo'], 422);
        }

        try {
            $this->useCase->update((int) $args['rangeId'], new ExamParameterRangeDto(
                parameterId:  (int) $args['parameterId'],
                reactivo:     trim($body['reactivo']),
                valorMinRef:  isset($body['valorMinRef']) ? (float) $body['valorMinRef'] : null,
                valorMaxRef:  isset($body['valorMaxRef']) ? (float) $body['valorMaxRef'] : null,
                sexo:         $body['sexo']    ?? '*',
                edadMin:      isset($body['edadMin']) ? (int) $body['edadMin'] : null,
                edadMax:      isset($body['edadMax']) ? (int) $body['edadMax'] : null,
            ));

            return $this->json($response, ['message' => 'Rango actualizado']);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'no encontrado') ? 404 : 422;
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }
    }

    public function deactivate(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface {
        try {
            $this->useCase->deactivate((int) $args['rangeId']);
            return $this->json($response, ['message' => 'Rango desactivado']);
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
