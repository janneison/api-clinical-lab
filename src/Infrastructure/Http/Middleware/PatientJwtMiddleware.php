<?php

namespace ClinicalLab\Infrastructure\Http\Middleware;

use ClinicalLab\Application\UseCase\PatientPortalUseCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Psr7\Response;

/**
 * Valida el JWT emitido exclusivamente para pacientes (issuer: api-clinical-lab-patient).
 * Inyecta los claims como atributo 'patient' en el request.
 */
class PatientJwtMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly PatientPortalUseCase $portalUseCase)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');

        if (!str_starts_with($header, 'Bearer ')) {
            return $this->unauthorized('Token de paciente ausente');
        }

        $token = substr($header, 7);

        try {
            $claims = $this->portalUseCase->validateToken($token);
        } catch (RuntimeException $e) {
            return $this->unauthorized($e->getMessage());
        }

        $request = $request->withAttribute('patient', $claims);

        return $handler->handle($request);
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode(['error' => $message], JSON_THROW_ON_ERROR));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }
}
