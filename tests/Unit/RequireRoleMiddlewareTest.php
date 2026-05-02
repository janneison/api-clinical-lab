<?php

declare(strict_types=1);

namespace Tests\Unit;

use ClinicalLab\Domain\Entity\Role;
use ClinicalLab\Infrastructure\Http\Middleware\RequireRoleMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Response;

class RequireRoleMiddlewareTest extends TestCase
{
    private function makeRequest(array $auth): ServerRequestInterface
    {
        return (new RequestFactory())
            ->createRequest('GET', '/test')
            ->withAttribute('auth', $auth);
    }

    private function okHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response());
        return $handler;
    }

    public function testAllowsRequestWhenRoleMatches(): void
    {
        $middleware = new RequireRoleMiddleware([Role::ADMIN, Role::LAB_OPERATOR]);
        $request    = $this->makeRequest(['role' => Role::LAB_OPERATOR]);

        $response = $middleware->process($request, $this->okHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testReturns403WhenRoleNotAllowed(): void
    {
        $middleware = new RequireRoleMiddleware([Role::ADMIN]);
        $request    = $this->makeRequest(['role' => Role::VIEWER]);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testReturns403WhenAuthAttributeMissing(): void
    {
        $middleware = new RequireRoleMiddleware([Role::ADMIN]);
        $request    = (new RequestFactory())->createRequest('GET', '/test'); // sin auth

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testAllowsAllRolesInList(): void
    {
        $allowed    = [Role::ADMIN, Role::LAB_OPERATOR, Role::ALIADO_OPERATOR, Role::VIEWER];
        $middleware = new RequireRoleMiddleware($allowed);

        foreach ($allowed as $role) {
            $request  = $this->makeRequest(['role' => $role]);
            $response = $middleware->process($request, $this->okHandler());
            $this->assertSame(200, $response->getStatusCode(), "Falló para rol: $role");
        }
    }
}
