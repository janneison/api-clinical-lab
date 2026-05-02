<?php

namespace ClinicalLab\Infrastructure\Http\Middleware;

use ClinicalLab\Domain\Service\TokenServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Psr7\Response;

class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly TokenServiceInterface $tokenService)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');

        if (!str_starts_with($header, 'Bearer ')) {
            return $this->unauthorized('Token de autorización ausente');
        }

        $token = substr($header, 7);

        try {
            $claims = $this->tokenService->validate($token);
        } catch (RuntimeException $e) {
            return $this->unauthorized($e->getMessage());
        }

        // Inyectar claims en el request para que los controladores los lean
        $request = $request->withAttribute('auth', $claims);

        return $handler->handle($request);
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode(['error' => $message], JSON_THROW_ON_ERROR));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }
}
