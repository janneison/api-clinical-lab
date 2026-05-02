<?php

namespace ClinicalLab\Infrastructure\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class RequireRoleMiddleware implements MiddlewareInterface
{
    /** @param string[] $allowedRoles */
    public function __construct(private readonly array $allowedRoles)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $auth = $request->getAttribute('auth');
        $role = $auth['role'] ?? null;

        if (!$role || !in_array($role, $this->allowedRoles, true)) {
            $response = new Response();
            $response->getBody()->write(json_encode(
                ['error' => 'No tienes permisos para esta acción'],
                JSON_THROW_ON_ERROR
            ));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
